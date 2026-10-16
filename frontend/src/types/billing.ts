import { Lease } from './lease';

export type ChargeCategory = 'CANTEEN' | 'APPLIANCE' | 'OTHER';
export type ChargeStatus = 'pending' | 'invoiced' | 'void';
export type InvoiceStatus = 'unpaid' | 'partial' | 'paid' | 'overdue';

export interface Charge {
  id: number;
  lease_id: number;
  category: ChargeCategory;
  description?: string | null;
  amount: number | string;
  status: ChargeStatus;
  created_at?: string;
  updated_at?: string;
  lease?: Lease;
}

export interface InvoiceItem {
  id: number;
  invoice_id: number;
  description: string;
  category: string;
  amount: number | string;
}

export interface PaymentRecord {
  id: number;
  invoice_id: number;
  amount: number | string;
  payment_method: string;
  reference_number?: string | null;
  notes?: string | null;
  recorded_by?: number;
  created_at: string;
}

export interface Invoice {
  id: number;
  lease_id: number;
  invoice_number?: string;
  billing_period: string;
  base_rent: number | string;
  additional_charges_total: number | string;
  total_amount_due: number | string;
  amount_paid: number | string;
  balance: number | string;
  due_date: string;
  status: InvoiceStatus;
  notes?: string | null;
  created_at?: string;
  updated_at?: string;
  lease?: Lease;
  items?: InvoiceItem[];
  payments?: PaymentRecord[];
}

export interface ChargeFormData {
  lease_id: number;
  category: ChargeCategory;
  description: string;
  amount: number;
}

export interface PaymentFormData {
  amount: number;
  method: string;
  notes?: string;
}

