import { Admin, Customer, Flash } from './models';

declare module '@inertiajs/core' {
    interface PageProps {
        auth: {
            admin?: Admin;
            customer?: Customer;
        };
        flash: Flash;
        ziggy?: Record<string, unknown>;
    }
}
