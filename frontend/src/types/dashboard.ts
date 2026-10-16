export interface LandlordDashboardData {
  occupancy: {
    occupied: number;
    total: number;
    percent: number;
  };
  available_rooms: number;
  active_leases: number;
  tenant_count: number;
  pending_inquiries: number;
  monthly_revenue: number | string;
}
