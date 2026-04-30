export interface Admin {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    last_login_at: string | null;
    created_at: string;
    roles?: { name: string }[];
}

export interface Customer {
    id: number;
    name: string;
    phone: string;
    phone_verified_at: string | null;
    gaspoints_balance: number;
    referral_code: string;
    is_active: boolean;
    created_at: string;
}

export interface CustomerAddress {
    id: number;
    customer_id: number;
    label: 'Home' | 'Office' | 'Restaurant' | 'Other';
    latitude: number;
    longitude: number;
    description: string | null;
    is_default: boolean;
}

export interface CylinderSize {
    id: number;
    name: string;
    weight_kg: number;
    sort_order: number;
    is_commercial: boolean;
    is_active: boolean;
    price?: CylinderPrice;
    stock_level?: StockLevel;
}

export interface GasBrand {
    id: number;
    name: string;
    logo_url: string | null;
    is_active: boolean;
}

export interface CylinderPrice {
    id: number;
    size_id: number;
    gas_refill_price: number;
    new_cylinder_price: number;
    new_gas_fill_price: number;
    delivery_fee: number;
}

export interface AddonGroup {
    id: number;
    size_id: number;
    name: string;
    selection_type: 'multi' | 'single';
    sort_order: number;
    is_active: boolean;
    items?: AddonItem[];
}

export interface AddonItem {
    id: number;
    group_id: number;
    name: string;
    description: string | null;
    price: number;
    photo_url: string | null;
    sort_order: number;
    is_active: boolean;
}

export interface StockLevel {
    id: number;
    size_id: number;
    filled_count: number;
    empty_count: number;
    low_stock_threshold: number;
    status: 'ok' | 'low' | 'critical' | 'out';
}

export interface Rider {
    id: number;
    name: string;
    phone: string;
    avatar_url: string | null;
    is_safety_certified: boolean;
    certification_date: string | null;
    is_active: boolean;
    is_available: boolean;
    avg_rating: number;
    total_deliveries: number;
    current_latitude: number | null;
    current_longitude: number | null;
}

export type OrderStatus =
    | 'pending'
    | 'rider_assigned'
    | 'picked_up'
    | 'on_the_way'
    | 'correction_in_progress'
    | 'delivered'
    | 'cancelled';

export type IssueType =
    | 'out_of_stock'
    | 'wrong_cylinder'
    | 'rider_no_show'
    | 'payment_dispute'
    | 'damaged_cylinder';

export interface Order {
    id: number;
    order_number: string;
    customer_id: number;
    rider_id: number | null;
    size_id: number;
    brand_id: number | null;
    order_type: 'swap' | 'new_cylinder';
    status: OrderStatus;
    gas_price: number;
    cylinder_price: number;
    delivery_fee: number;
    addons_total: number;
    total_amount: number;
    formatted_total: string;
    payment_method: 'cash' | 'mpesa';
    payment_status: 'pending' | 'collected' | 'disputed' | 'refunded';
    delivery_lat: number;
    delivery_lng: number;
    delivery_notes: string | null;
    has_issue: boolean;
    issue_type: IssueType | null;
    issue_description: string | null;
    rider_assigned_at: string | null;
    picked_up_at: string | null;
    delivered_at: string | null;
    cancelled_at: string | null;
    cancel_reason: string | null;
    created_at: string;
    customer?: Customer;
    rider?: Rider;
    size?: CylinderSize;
    brand?: GasBrand;
}

export interface GasPointsTransaction {
    id: number;
    type: 'earned' | 'redeemed' | 'bonus' | 'referral' | 'referral_bonus';
    points: number;
    balance_after: number;
    description: string;
    created_at: string;
}

export interface Flash {
    success?: string;
    error?: string;
    warning?: string;
}
