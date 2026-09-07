<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Sms\SendCampaignRequest;
use App\Models\Customer;
use App\Models\SmsCampaign;
use App\Services\Admin\SmsAudienceService;
use App\Services\Admin\SmsCampaignService;
use App\Support\SmsSegments;
use App\Support\Utf8Sanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SmsCampaignController extends Controller
{
    public function __construct(
        private readonly SmsCampaignService $campaigns,
        private readonly SmsAudienceService $audience,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Sms/Index', [
            'campaigns' => SmsCampaign::with('admin:id,name')
                ->latest()
                ->paginate(20)
                ->through(fn (SmsCampaign $c) => $this->sanitize([
                    'id' => $c->id,
                    'title' => $c->title,
                    'message' => $c->message,
                    'kind' => $c->kind,
                    'audience' => $this->audience->options()[$c->audience] ?? 'Hand-picked',
                    'recipient_count' => $c->recipient_count,
                    'skipped_opted_out' => $c->skipped_opted_out,
                    'segments_per_message' => $c->segments_per_message,
                    'total_segments' => $c->total_segments,
                    'status' => $c->status,
                    'sent_by' => $c->admin?->name,
                    'sent_ago' => $c->created_at->diffForHumans(),
                ])),
            // Jobs still waiting on the bulk queue. Surfaced because a campaign
            // stuck at "queued" with work outstanding almost always means the
            // worker is not covering the `bulk` queue at all.
            'pending_jobs' => $this->pendingBulkJobs(),
            'opted_out_total' => Customer::whereNotNull('sms_opt_out_at')->count(),
        ]);
    }

    public function create(Request $request): Response
    {
        // Arrives from the customer list when names were ticked there.
        $selected = collect(explode(',', (string) $request->query('customers')))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        return Inertia::render('Admin/Sms/Create', [
            'audiences' => $this->audience->options(),
            'selected' => $selected->all(),
            'selectedCustomers' => $selected->isEmpty() ? [] : $this->sanitize(
                Customer::whereIn('id', $selected)
                    ->get(['id', 'name', 'phone', 'sms_opt_out_at'])
                    ->map(fn (Customer $c) => [
                        'id' => $c->id,
                        'name' => $c->name,
                        'phone' => $c->phone,
                        'opted_out' => $c->sms_opt_out_at !== null,
                    ])->all(),
            ),
            'optOutLine' => SmsCampaignService::OPT_OUT_LINE,
        ]);
    }

    /**
     * Customer search for the composer's picker.
     *
     * Capped rather than paginated: this is a "find the two people I mean"
     * box, not a browse. Anyone reaching for hundreds of recipients wants an
     * audience filter instead, which is one control away.
     */
    public function customers(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q'));

        $customers = Customer::query()
            ->where('is_active', true)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->when($search !== '', fn ($q) => $q->where(
                fn ($inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"),
            ))
            // Newest first with no search: the people just added are the ones
            // most likely to be wanted.
            ->orderByDesc('created_at')
            ->limit(25)
            ->get(['id', 'name', 'phone', 'sms_opt_out_at']);

        return response()->json(
            $this->sanitize($customers->map(fn (Customer $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'opted_out' => $c->sms_opt_out_at !== null,
            ])->all()),
        );
    }

    /**
     * Live cost of the message being typed.
     *
     * Server-side so the number the admin confirms is the number the sender
     * will actually use — the composer's own count is only a hint.
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:2000'],
            'kind' => ['required', 'in:promo,info'],
            'audience' => ['required', 'string'],
            'customer_ids' => ['array'],
            'customer_ids.*' => ['integer'],
        ]);

        return response()->json($this->campaigns->preview(
            $data['message'] ?? '',
            $data['kind'],
            $data['audience'],
            $data['customer_ids'] ?? [],
        ));
    }

    public function store(SendCampaignRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $campaign = $this->campaigns->dispatchCampaign(
            $data['title'],
            $data['message'],
            $data['kind'],
            $data['audience'],
            $data['customer_ids'] ?? [],
            auth('admin')->id(),
        );

        return redirect()->route('admin.sms.index')->with(
            'success',
            "Campaign queued: {$campaign->recipient_count} recipients, {$campaign->total_segments} SMS segments.",
        );
    }

    public function show(SmsCampaign $campaign): Response
    {
        return Inertia::render('Admin/Sms/Show', [
            'campaign' => $this->sanitize([
                'id' => $campaign->id,
                'title' => $campaign->title,
                'message' => $campaign->message,
                'kind' => $campaign->kind,
                'audience' => $this->audience->options()[$campaign->audience] ?? 'Hand-picked',
                'recipient_count' => $campaign->recipient_count,
                'skipped_opted_out' => $campaign->skipped_opted_out,
                'segments_per_message' => $campaign->segments_per_message,
                'total_segments' => $campaign->total_segments,
                'status' => $campaign->status,
                'sent_by' => $campaign->admin?->name,
                'sent_at' => $campaign->created_at->format('D, d M Y · H:i'),
                'encoding' => SmsSegments::measure($campaign->message)['encoding'],
            ]),
            'recipients' => $this->sanitize(
                $campaign->recipients()
                    ->orderBy('name')
                    ->paginate(50)
                    ->through(fn (Customer $c) => [
                        'id' => $c->id,
                        'name' => $c->name,
                        'phone' => $c->pivot->phone,
                    ])
                    ->toArray(),
            ),
        ]);
    }

    /** Toggle a customer's marketing consent by hand. */
    public function toggleOptOut(Customer $customer): RedirectResponse
    {
        $optingOut = $customer->sms_opt_out_at === null;

        $customer->update([
            'sms_opt_out_at' => $optingOut ? now() : null,
            'sms_opt_out_source' => $optingOut ? 'admin' : null,
        ]);

        return back()->with(
            'success',
            $optingOut
                ? "{$customer->name} will no longer receive promotional SMS."
                : "{$customer->name} will receive promotional SMS again.",
        );
    }

    private function pendingBulkJobs(): int
    {
        if (config('queue.default') !== 'database') {
            return 0;
        }

        try {
            return DB::table('jobs')->where('queue', 'bulk')->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function sanitize(mixed $value): mixed
    {
        return Utf8Sanitizer::clean($value);
    }
}
