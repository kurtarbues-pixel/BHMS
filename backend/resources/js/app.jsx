import React, { useCallback, useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { api, fetchAll, getToken, listData, saveToken } from './api';
import '../css/app.css';

const navItems = [
    { id: 'dashboard', label: 'Dashboard', roles: ['admin', 'landlord', 'staff'] },
    { id: 'rooms', label: 'Rooms', roles: ['admin', 'landlord', 'staff', 'tenant'] },
    { id: 'leases', label: 'Tenants & occupancy', roles: ['admin', 'landlord', 'staff', 'tenant'] },
    { id: 'inquiries', label: 'Room requests', roles: ['admin', 'landlord', 'staff', 'tenant'] },
    { id: 'billing', label: 'Charges & invoices', roles: ['admin', 'landlord', 'staff', 'tenant'] },
    { id: 'accounts', label: 'User accounts', roles: ['admin'] },
    { id: 'activity', label: 'Activity log', roles: ['admin'] },
];

const money = (amount) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(amount || 0));
const today = () => new Date().toISOString().slice(0, 10);
const thisMonth = () => new Date().toISOString().slice(0, 7);

function App() {
    const [user, setUser] = useState(null);
    const [checking, setChecking] = useState(Boolean(getToken()));
    const [page, setPage] = useState('dashboard');
    const [notice, setNotice] = useState(null);
    const [dark, setDark] = useState(localStorage.getItem('bhms-theme') === 'dark');

    useEffect(() => {
        document.documentElement.dataset.theme = dark ? 'dark' : 'light';
        localStorage.setItem('bhms-theme', dark ? 'dark' : 'light');
    }, [dark]);

    useEffect(() => {
        const unauthorized = () => setUser(null);
        window.addEventListener('bhms:unauthorized', unauthorized);
        if (getToken()) {
            api('/me').then((data) => setUser(data.user)).catch(() => setUser(null)).finally(() => setChecking(false));
        }
        return () => window.removeEventListener('bhms:unauthorized', unauthorized);
    }, []);

    const announce = useCallback((message, kind = 'success') => {
        setNotice({ message, kind });
        window.setTimeout(() => setNotice(null), 5000);
    }, []);

    const handleLogin = async (credentials, registering = false) => {
        const result = await api(registering ? '/register' : '/login', { method: 'POST', body: credentials });
        saveToken(result.token);
        setUser(result.user);
        setPage(result.user.role === 'tenant' ? 'rooms' : 'dashboard');
        announce(registering ? 'Your account is ready.' : 'You are signed in.');
    };

    const signOut = async () => {
        try { await api('/logout', { method: 'POST' }); } catch { /* Clear the local session even when the API is offline. */ }
        saveToken(null);
        setUser(null);
        setPage('dashboard');
    };

    if (checking) return <div className="screen-message">Connecting to the system…</div>;
    if (!user) return <Login onSubmit={handleLogin} />;

    const allowed = navItems.filter((item) => item.roles.includes(user.role));
    const currentPage = allowed.some((item) => item.id === page) ? page : allowed[0]?.id;

    return (
        <div className="app-shell">
            <aside className="sidebar">
                <a className="brand" href="#dashboard" onClick={(event) => { event.preventDefault(); setPage('dashboard'); }}>
                    <span className="brand-mark" aria-hidden="true">BH</span>
                    <span><strong>Boarding House</strong><small>Management System</small></span>
                </a>
                <nav className="nav-list" aria-label="Main navigation">
                    {allowed.map((item) => <button key={item.id} className={currentPage === item.id ? 'nav-link active' : 'nav-link'} onClick={() => setPage(item.id)}>{item.label}</button>)}
                </nav>
                <div className="sidebar-bottom"><span className="role-chip">{user.role}</span><span className="sidebar-note">Your data is managed securely.</span></div>
            </aside>

            <main className="main-area">
                <header className="topbar">
                    <div><p className="eyebrow">BOARDING HOUSE MANAGEMENT</p><h1>{allowed.find((item) => item.id === currentPage)?.label || 'Dashboard'}</h1></div>
                    <div className="top-actions">
                        <button className="button quiet" onClick={() => setDark((value) => !value)} aria-label={`Switch to ${dark ? 'light' : 'dark'} mode`}>{dark ? 'Light mode' : 'Dark mode'}</button>
                        <div className="account"><strong>{user.name}</strong><span>{user.email}</span></div>
                        <button className="button secondary" onClick={signOut}>Sign out</button>
                    </div>
                </header>

                {notice && <div className={`notice ${notice.kind}`} role="status">{notice.message}<button onClick={() => setNotice(null)} aria-label="Dismiss message">×</button></div>}
                <div className="page-content">
                    {currentPage === 'dashboard' && <Dashboard user={user} onNavigate={setPage} />}
                    {currentPage === 'rooms' && <Rooms user={user} announce={announce} />}
                    {currentPage === 'leases' && <Leases user={user} announce={announce} />}
                    {currentPage === 'inquiries' && <Inquiries user={user} announce={announce} />}
                    {currentPage === 'billing' && <Billing user={user} announce={announce} />}
                    {currentPage === 'accounts' && <Accounts announce={announce} />}
                    {currentPage === 'activity' && <Activity />}
                </div>
            </main>
        </div>
    );
}

function Login({ onSubmit }) {
    const [register, setRegister] = useState(false);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const submit = async (event) => {
        event.preventDefault();
        setError(''); setBusy(true);
        const form = new FormData(event.currentTarget);
        const name = String(form.get('name') || '').trim();
        const password = String(form.get('password') || '');
        try {
            await onSubmit(register
                ? { name, email: form.get('email'), phone: form.get('phone'), role: 'tenant', password, password_confirmation: password }
            : { identifier: form.get('identifier'), password }, register);
        } catch (exception) { setError(exception.message); }
        finally { setBusy(false); }
    };
    return <main className="auth-page">
        <section className="auth-card" aria-labelledby="auth-heading">
            <div className="brand-mark auth-mark" aria-hidden="true">BH</div>
            <p className="eyebrow">BOARDING HOUSE MANAGEMENT</p>
            <h1 id="auth-heading">{register ? 'Create your tenant account' : 'Welcome back'}</h1>
            <p className="muted">{register ? 'Register to send room requests and view your invoices.' : 'Sign in to manage your boarding house.'}</p>
            {error && <div className="notice error" role="alert">{error}</div>}
            <form className="form-stack" onSubmit={submit}>
                {register && <>
                    <Field label="Full name"><input name="name" autoComplete="name" required maxLength="255" /></Field>
                    <Field label="Phone number"><input name="phone" autoComplete="tel" maxLength="25" /></Field>
                </>}
                <Field label={register ? 'Email address' : 'Email address or username'}><input type={register ? 'email' : 'text'} name={register ? 'email' : 'identifier'} autoComplete={register ? 'email' : 'username'} required /></Field>
                <Field label="Password"><input type="password" name="password" autoComplete={register ? 'new-password' : 'current-password'} minLength="8" required /></Field>
                <button className="button primary full" disabled={busy}>{busy ? 'Please wait…' : register ? 'Create account' : 'Sign in'}</button>
            </form>
            <p className="auth-switch">{register ? 'Already registered?' : 'Tenant without an account?'} <button onClick={() => { setRegister((value) => !value); setError(''); }}>{register ? 'Sign in' : 'Create tenant account'}</button></p>
        </section>
    </main>;
}

function Field({ label, children, hint }) {
    return <label className="field"><span>{label}</span>{children}{hint && <small>{hint}</small>}</label>;
}

function Panel({ title, action, children, className = '' }) {
    return <section className={`panel ${className}`}><div className="panel-heading"><h2>{title}</h2>{action}</div>{children}</section>;
}

function Empty({ children = 'Nothing to show yet.' }) { return <div className="empty-state">{children}</div>; }
function ErrorState({ error, retry }) { return <div className="error-state" role="alert"><p>{error}</p>{retry && <button className="button secondary" onClick={retry}>Try again</button>}</div>; }
function Loading() { return <div className="loading" role="status">Loading information…</div>; }

function Stat({ label, value, detail }) {
    return <article className="stat"><span>{label}</span><strong>{value}</strong>{detail && <small>{detail}</small>}</article>;
}

function Dashboard({ user, onNavigate }) {
    const [data, setData] = useState(null);
    const [error, setError] = useState('');
    const tenant = user.role === 'tenant';
    const load = useCallback(async () => {
        setError('');
        try {
            if (tenant) {
                const [rooms, leases, invoices] = await Promise.all([fetchAll('/rooms?per_page=50'), fetchAll('/leases'), fetchAll('/invoices')]);
                setData({ rooms, leases, invoices });
            } else setData(await api('/landlord/dashboard'));
        } catch (exception) { setError(exception.message); }
    }, [tenant]);
    useEffect(() => { load(); }, [load]);
    if (error) return <ErrorState error={error} retry={load} />;
    if (!data) return <Loading />;

    if (tenant) {
        const active = data.leases.filter((lease) => lease.status === 'active').length;
        const openInvoices = data.invoices.filter((invoice) => invoice.status.toLowerCase() !== 'paid').length;
        return <>
            <div className="welcome-block"><div><h2>Hello, {user.name}</h2><p>Here is a quick look at your room and account.</p></div></div>
            <div className="stats-grid"><Stat label="Available rooms" value={data.rooms.filter((room) => room.status === 'available').length} /><Stat label="Active leases" value={active} /><Stat label="Invoices to review" value={openInvoices} /></div>
            <div className="quick-grid"><button className="quick-card" onClick={() => onNavigate('rooms')}><strong>Browse rooms</strong><span>See rooms available for rent</span></button><button className="quick-card" onClick={() => onNavigate('inquiries')}><strong>Room requests</strong><span>Check request decisions</span></button><button className="quick-card" onClick={() => onNavigate('billing')}><strong>Billing</strong><span>Review invoices and balances</span></button></div>
        </>;
    }

    return <>
        <div className="welcome-block"><div><h2>Good day, {user.name}</h2><p>Here is the current overview of your property operations.</p></div><button className="button secondary" onClick={load}>Refresh</button></div>
        <div className="stats-grid"><Stat label="Rooms occupied" value={`${data.occupancy.occupied} / ${data.occupancy.total}`} detail={`${data.occupancy.percent}% occupancy`} /><Stat label="Rooms available" value={data.available_rooms} /><Stat label="Active leases" value={data.active_leases} /><Stat label="Tenants" value={data.tenant_count} /><Stat label="Requests to review" value={data.pending_inquiries} /><Stat label="Collected this month" value={money(data.monthly_revenue)} /></div>
        <Panel title="Common tasks"><div className="quick-grid"><button className="quick-card" onClick={() => onNavigate('rooms')}><strong>Manage rooms</strong><span>Add rooms or update availability</span></button><button className="quick-card" onClick={() => onNavigate('leases')}><strong>Record a new lease</strong><span>Assign a tenant to an available room</span></button><button className="quick-card" onClick={() => onNavigate('billing')}><strong>Prepare invoices</strong><span>Add charges or generate monthly invoices</span></button></div></Panel>
    </>;
}

function Rooms({ user, announce }) {
    const canManage = user.role !== 'tenant';
    const [rooms, setRooms] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [editing, setEditing] = useState(null);
    const load = useCallback(async () => {
        setLoading(true); setError('');
        try { setRooms(await fetchAll('/rooms?per_page=50')); }
        catch (exception) { setError(exception.message); }
        finally { setLoading(false); }
    }, []);
    useEffect(() => { load(); }, [load]);

    const saveRoom = async (event) => {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        const payload = {
            room_number: form.get('room_number'), title: form.get('title'), type: form.get('type') || null,
            description: form.get('description') || null, location: form.get('location'), price: Number(form.get('price')),
            status: form.get('status'), amenities: String(form.get('amenities') || '').split(',').map((x) => x.trim()).filter(Boolean),
        };
        try {
            if (editing?.id) await api(`/rooms/${editing.id}`, { method: 'PUT', body: payload });
            else await api('/rooms', { method: 'POST', body: payload });
            setEditing(null); await load(); announce(editing?.id ? 'Room updated.' : 'Room added.');
        } catch (exception) { announce(exception.message, 'error'); }
    };

    const setStatus = async (room, status) => {
        try { await api(`/rooms/${room.id}/availability`, { method: 'PATCH', body: { status } }); await load(); announce('Room status updated.'); }
        catch (exception) { announce(exception.message, 'error'); }
    };

    const removeRoom = async (room) => {
        if (!window.confirm(`Delete room ${room.room_number}?`)) return;
        try { await api(`/rooms/${room.id}`, { method: 'DELETE' }); await load(); announce('Room deleted.'); }
        catch (exception) { announce(exception.message, 'error'); }
    };

    return <>
        <div className="welcome-block"><div><h2>Room inventory</h2><p>Review prices, room types, and availability.</p></div>{canManage && <button className="button primary" onClick={() => setEditing({})}>Add room</button>}</div>
        {editing && canManage && <Panel title={editing.id ? 'Edit room' : 'Add room'} className="form-panel">
            <form className="form-grid" onSubmit={saveRoom}>
                <Field label="Room number"><input name="room_number" defaultValue={editing.room_number || ''} required maxLength="20" /></Field>
                <Field label="Room name"><input name="title" defaultValue={editing.title || ''} required /></Field>
                <Field label="Room type"><input name="type" defaultValue={editing.type || ''} placeholder="Single, bedspace, family…" /></Field>
                <Field label="Location"><input name="location" defaultValue={editing.location || ''} required /></Field>
                <Field label="Monthly rent (₱)"><input name="price" type="number" min="0.01" step="0.01" defaultValue={editing.price || ''} required /></Field>
                <Field label="Availability"><select name="status" defaultValue={editing.status === 'occupied' ? 'occupied' : (editing.status || 'available')}><option value="available">Available</option><option value="maintenance">Maintenance</option>{editing.status === 'occupied' && <option value="occupied" disabled>Occupied by lease</option>}</select></Field>
                <Field label="Amenities (comma separated)"><input name="amenities" defaultValue={(editing.amenities || []).join(', ')} /></Field>
                <Field label="Description"><textarea name="description" rows="3" defaultValue={editing.description || ''} /></Field>
                <div className="form-actions"><button className="button primary">Save room</button><button type="button" className="button secondary" onClick={() => setEditing(null)}>Cancel</button></div>
            </form>
        </Panel>}
        {error ? <ErrorState error={error} retry={load} /> : loading ? <Loading /> : rooms.length === 0 ? <Empty>No rooms are listed yet.</Empty> : <div className="room-grid">{rooms.map((room) => <article className="room-card" key={room.id}>
            <div className="room-card-top"><span className={`status ${room.status}`}>{room.status}</span><span className="room-number">Room {room.room_number}</span></div>
            <h3>{room.title}</h3><p className="muted">{room.type || 'Room'} · {room.location}</p><strong className="room-price">{money(room.price)} <small>per month</small></strong>
            {room.amenities?.length > 0 && <p className="amenities">{room.amenities.join(' · ')}</p>}
            {canManage ? <div className="room-actions"><button className="button secondary" onClick={() => setEditing(room)}>Edit</button>{room.status === 'maintenance' ? <button className="button secondary" onClick={() => setStatus(room, 'available')}>Mark available</button> : room.status === 'available' ? <button className="button secondary" onClick={() => setStatus(room, 'maintenance')}>Set maintenance</button> : <span className="muted">Occupied by active lease</span>}<button className="button danger-outline" onClick={() => removeRoom(room)}>Delete</button></div>
                : room.status === 'available' && <TenantInquiry room={room} announce={announce} />}
        </article>)}</div>}
    </>;
}

function TenantInquiry({ room, announce }) {
    const [busy, setBusy] = useState(false);
    const send = async () => {
        setBusy(true);
        try { await api(`/rooms/${room.id}/inquiries`, { method: 'POST', body: { message: `I am interested in renting room ${room.room_number}. Please contact me with the next steps.` } }); announce('Your room request was sent.'); }
        catch (exception) { announce(exception.message, 'error'); }
        finally { setBusy(false); }
    };
    return <button className="button primary" disabled={busy} onClick={send}>{busy ? 'Sending…' : 'Request this room'}</button>;
}

function Leases({ user, announce }) {
    const canManage = user.role !== 'tenant';
    const [leases, setLeases] = useState([]);
    const [rooms, setRooms] = useState([]);
    const [tenants, setTenants] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [showForm, setShowForm] = useState(false);
    const load = useCallback(async () => {
        setLoading(true); setError('');
        try {
            const [leaseRows, roomRows] = await Promise.all([fetchAll('/leases'), fetchAll('/rooms?per_page=50')]);
            setLeases(leaseRows); setRooms(roomRows);
            if (canManage) setTenants(listData(await api('/tenants')));
        } catch (exception) { setError(exception.message); }
        finally { setLoading(false); }
    }, [canManage]);
    useEffect(() => { load(); }, [load]);

    const createLease = async (event) => {
        event.preventDefault(); const form = new FormData(event.currentTarget);
        const payload = { room_id: Number(form.get('room_id')), tenant_id: Number(form.get('tenant_id')), start_date: form.get('start_date'), end_date: form.get('end_date') || null, monthly_rent: form.get('monthly_rent') ? Number(form.get('monthly_rent')) : null };
        try { await api('/leases', { method: 'POST', body: payload }); setShowForm(false); await load(); announce('Lease created and room marked occupied.'); }
        catch (exception) { announce(exception.message, 'error'); }
    };
    const endLease = async (lease) => {
        if (!window.confirm(`End the lease for ${lease.tenant?.name || 'this tenant'}?`)) return;
        try { await api(`/leases/${lease.id}/end`, { method: 'POST' }); await load(); announce('Lease ended and room made available.'); }
        catch (exception) { announce(exception.message, 'error'); }
    };

    const availableRooms = rooms.filter((room) => room.status === 'available');
    const active = leases.filter((lease) => lease.status === 'active');
    const history = leases.filter((lease) => lease.status !== 'active');
    return <>
        <div className="welcome-block"><div><h2>{canManage ? 'Tenants and occupancy' : 'My lease'}</h2><p>Review who is staying in each room and the lease dates.</p></div>{canManage && <button className="button primary" onClick={() => setShowForm((value) => !value)}>Create lease</button>}</div>
        {showForm && canManage && <Panel title="Create a lease" className="form-panel"><form className="form-grid" onSubmit={createLease}>
            <Field label="Tenant"><select name="tenant_id" required defaultValue=""><option value="" disabled>Select a tenant</option>{tenants.map((tenant) => <option key={tenant.id} value={tenant.id}>{tenant.name} · {tenant.email}</option>)}</select></Field>
            <Field label="Available room"><select name="room_id" required defaultValue=""><option value="" disabled>Select a room</option>{availableRooms.map((room) => <option key={room.id} value={room.id}>Room {room.room_number} · {room.title} · {money(room.price)}/mo</option>)}</select></Field>
            <Field label="Start date"><input type="date" name="start_date" defaultValue={today()} required /></Field>
            <Field label="End date (optional)"><input type="date" name="end_date" /></Field>
            <Field label="Monthly rent override (optional)"><input type="number" name="monthly_rent" min="0.01" step="0.01" placeholder="Uses room price if blank" /></Field>
            <div className="form-actions"><button className="button primary" disabled={!tenants.length || !availableRooms.length}>Save lease</button><button type="button" className="button secondary" onClick={() => setShowForm(false)}>Cancel</button></div>
            {tenants.length === 0 && <p className="muted">Create an active tenant account in User accounts first.</p>}
        </form></Panel>}
        {error ? <ErrorState error={error} retry={load} /> : loading ? <Loading /> : <>
            <Panel title={`Active leases (${active.length})`}>{active.length ? <LeaseTable leases={active} canManage={canManage} onEnd={endLease} /> : <Empty>No active leases.</Empty>}</Panel>
            <Panel title="Past leases" className="spaced-panel">{history.length ? <LeaseTable leases={history} canManage={false} /> : <Empty>No past leases.</Empty>}</Panel>
        </>}
    </>;
}

function LeaseTable({ leases, canManage, onEnd }) {
    return <div className="table-scroll"><table><thead><tr><th>Tenant</th><th>Room</th><th>Monthly rent</th><th>Start</th><th>End</th><th>Status</th>{canManage && <th>Actions</th>}</tr></thead><tbody>{leases.map((lease) => <tr key={lease.id}><td><strong>{lease.tenant?.name}</strong><small>{lease.tenant?.email}</small></td><td>{lease.room?.room_number} · {lease.room?.title}</td><td>{money(lease.monthly_rent)}</td><td>{lease.start_date}</td><td>{lease.end_date || 'Ongoing'}</td><td><span className={`status ${lease.status}`}>{lease.status}</span></td>{canManage && <td><button className="button danger-outline" onClick={() => onEnd(lease)}>End lease</button></td>}</tr>)}</tbody></table></div>;
}

function Inquiries({ user, announce }) {
    const canManage = user.role !== 'tenant';
    const [inquiries, setInquiries] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const load = useCallback(async () => {
        setLoading(true); setError('');
        try { setInquiries(await fetchAll('/inquiries')); }
        catch (exception) { setError(exception.message); }
        finally { setLoading(false); }
    }, []);
    useEffect(() => { load(); }, [load]);
    const decide = async (inquiry, action) => {
        try { await api(`/inquiries/${inquiry.id}/${action}`, { method: 'POST' }); await load(); announce(action === 'approve' ? 'Request approved and lease created.' : 'Request declined.'); }
        catch (exception) { announce(exception.message, 'error'); }
    };
    return <>
        <div className="welcome-block"><div><h2>{canManage ? 'Room requests' : 'My room requests'}</h2><p>{canManage ? 'Review incoming requests and approve a tenant to create a lease.' : 'See the status of your room requests.'}</p></div><button className="button secondary" onClick={load}>Refresh</button></div>
        {error ? <ErrorState error={error} retry={load} /> : loading ? <Loading /> : inquiries.length === 0 ? <Empty>No room requests found.</Empty> : <div className="request-list">{inquiries.map((inquiry) => <article key={inquiry.id} className="request-card"><div><div className="row-heading"><h3>{inquiry.room?.title || `Room ${inquiry.room?.room_number}`}</h3><span className={`status ${inquiry.status.toLowerCase()}`}>{inquiry.status}</span></div><p className="muted">Room {inquiry.room?.room_number} · {inquiry.room?.location} · {money(inquiry.room?.price)}/month</p><p>{inquiry.message}</p><small>Tenant: {inquiry.tenant?.name} · {inquiry.tenant?.email}</small></div>{canManage && inquiry.status.toLowerCase() === 'pending' && <div className="inline-actions"><button className="button primary" onClick={() => decide(inquiry, 'approve')}>Approve and create lease</button><button className="button secondary" onClick={() => decide(inquiry, 'reject')}>Decline</button></div>}</article>)}</div>}
    </>;
}

function Billing({ user, announce }) {
    const canManage = user.role !== 'tenant';
    const [leases, setLeases] = useState([]);
    const [invoices, setInvoices] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [period, setPeriod] = useState(thisMonth());
    const [payment, setPayment] = useState(null);
    const load = useCallback(async () => {
        setLoading(true); setError('');
        try {
            const results = await Promise.all([fetchAll('/leases'), fetchAll('/invoices')]);
            setLeases(results[0]); setInvoices(results[1]);
        } catch (exception) { setError(exception.message); }
        finally { setLoading(false); }
    }, []);
    useEffect(() => { load(); }, [load]);
    const generate = async () => {
        try { const result = await api('/invoices/generate', { method: 'POST', body: { period } }); await load(); announce(`Invoices for ${result.period}: ${result.created} created, ${result.updated} updated.`); }
        catch (exception) { announce(exception.message, 'error'); }
    };
    const addCharge = async (event) => {
        event.preventDefault(); const formElement = event.currentTarget; const form = new FormData(formElement);
        try { await api(`/leases/${form.get('lease_id')}/charges`, { method: 'POST', body: { category: form.get('category'), description: form.get('description'), amount: Number(form.get('amount')) } }); formElement.reset(); await load(); announce('Charge added to the pending ledger.'); }
        catch (exception) { announce(exception.message, 'error'); }
    };
    const voidCharge = async (charge) => {
        if (!window.confirm('Remove this pending charge?')) return;
        try { await api(`/charges/${charge.id}/void`, { method: 'POST' }); await load(); announce('Pending charge removed.'); }
        catch (exception) { announce(exception.message, 'error'); }
    };
    const recordPayment = async (event) => {
        event.preventDefault(); const form = new FormData(event.currentTarget);
        try { await api(`/invoices/${payment.id}/payments`, { method: 'POST', body: { amount: Number(form.get('amount')), method: form.get('method'), notes: form.get('notes') } }); setPayment(null); await load(); announce('Payment recorded.'); }
        catch (exception) { announce(exception.message, 'error'); }
    };
    const pendingCharges = leases.flatMap((lease) => (lease.charges || []).filter((charge) => charge.status.toLowerCase() === 'pending').map((charge) => ({ ...charge, lease })));
    const activeLeases = leases.filter((lease) => lease.status === 'active');

    return <>
        <div className="welcome-block"><div><h2>Charges and invoices</h2><p>Record additional charges, prepare rent invoices, and track payments.</p></div><button className="button secondary" onClick={load}>Refresh</button></div>
        {canManage && <div className="billing-tools">
            <Panel title="Add a pending charge"><form className="form-grid" onSubmit={addCharge}>
                <Field label="Tenant and room"><select name="lease_id" required defaultValue=""><option value="" disabled>Select an active lease</option>{activeLeases.map((lease) => <option key={lease.id} value={lease.id}>{lease.tenant?.name} · Room {lease.room?.room_number}</option>)}</select></Field>
                <Field label="Category"><select name="category"><option value="CANTEEN">Canteen</option><option value="APPLIANCE">Appliance</option><option value="OTHER">Other</option></select></Field>
                <Field label="Description"><input name="description" maxLength="255" placeholder="Short reason for the charge" /></Field>
                <Field label="Amount (₱)"><input name="amount" type="number" min="0.01" step="0.01" required /></Field>
                <div className="form-actions"><button className="button primary" disabled={!activeLeases.length}>Save charge</button></div>
            </form></Panel>
            <Panel title="Generate monthly invoices"><div className="generate-row"><Field label="Billing period"><input type="month" value={period} onChange={(event) => setPeriod(event.target.value)} /></Field><button className="button primary" onClick={generate}>Generate invoices</button></div><p className="muted">Pending charges for active leases are added to the invoice for this month.</p></Panel>
        </div>}
        {error ? <ErrorState error={error} retry={load} /> : loading ? <Loading /> : <>
            {canManage && <Panel title={`Pending charges (${pendingCharges.length})`}>{pendingCharges.length ? <div className="table-scroll"><table><thead><tr><th>Tenant</th><th>Room</th><th>Charge</th><th>Category</th><th>Amount</th><th>Action</th></tr></thead><tbody>{pendingCharges.map((charge) => <tr key={charge.id}><td>{charge.lease.tenant?.name}</td><td>{charge.lease.room?.room_number}</td><td>{charge.description || 'Additional charge'}</td><td>{charge.category}</td><td>{money(charge.amount)}</td><td><button className="button danger-outline" onClick={() => voidCharge(charge)}>Remove</button></td></tr>)}</tbody></table></div> : <Empty>No pending charges.</Empty>}</Panel>}
            <Panel title="Invoices" className="spaced-panel">{invoices.length ? <div className="table-scroll"><table><thead><tr><th>Tenant</th><th>Room</th><th>Period</th><th>Rent</th><th>Charges</th><th>Total</th><th>Paid</th><th>Balance</th><th>Due</th><th>Status</th>{canManage && <th>Payment</th>}</tr></thead><tbody>{invoices.map((invoice) => <tr key={invoice.id}><td>{invoice.lease?.tenant?.name}</td><td>{invoice.lease?.room?.room_number}</td><td>{invoice.billing_period}</td><td>{money(invoice.base_rent)}</td><td>{money(invoice.additional_charges_total)}</td><td><strong>{money(invoice.total_amount_due)}</strong></td><td>{money(invoice.amount_paid)}</td><td>{money(invoice.balance)}</td><td>{invoice.due_date}</td><td><span className={`status ${invoice.status.toLowerCase()}`}>{invoice.status}</span></td>{canManage && <td>{invoice.status.toLowerCase() !== 'paid' && <button className="button secondary" onClick={() => setPayment(invoice)}>Record payment</button>}</td>}</tr>)}</tbody></table></div> : <Empty>No invoices have been generated.</Empty>}</Panel>
        </>}
        {payment && <div className="modal-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) setPayment(null); }}><section className="dialog" role="dialog" aria-modal="true" aria-labelledby="payment-heading"><h2 id="payment-heading">Record a payment</h2><p>Balance due: <strong>{money(payment.balance)}</strong></p><form className="form-stack" onSubmit={recordPayment}><Field label="Amount (₱)"><input name="amount" type="number" min="0.01" max={payment.balance} step="0.01" required defaultValue={payment.balance} /></Field><Field label="Payment method"><select name="method"><option value="cash">Cash</option><option value="bank transfer">Bank transfer</option><option value="e-wallet">E-wallet</option><option value="other">Other</option></select></Field><Field label="Notes"><input name="notes" maxLength="255" /></Field><div className="form-actions"><button className="button primary">Save payment</button><button type="button" className="button secondary" onClick={() => setPayment(null)}>Cancel</button></div></form></section></div>}
    </>;
}

function Accounts({ announce }) {
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [showForm, setShowForm] = useState(false);
    const load = useCallback(async () => {
        setLoading(true); setError('');
        try { setUsers(await fetchAll('/admin/users')); }
        catch (exception) { setError(exception.message); }
        finally { setLoading(false); }
    }, []);
    useEffect(() => { load(); }, [load]);
    const create = async (event) => {
        event.preventDefault(); const form = new FormData(event.currentTarget);
        try { await api('/admin/users', { method: 'POST', body: { name: form.get('name'), username: form.get('username') || null, email: form.get('email'), phone: form.get('phone'), role: form.get('role'), password: form.get('password') } }); setShowForm(false); await load(); announce('Account created.'); }
        catch (exception) { announce(exception.message, 'error'); }
    };
    const change = async (account, key, value) => {
        try { await api(`/admin/users/${account.id}`, { method: 'PATCH', body: { [key]: value } }); await load(); announce('Account updated.'); }
        catch (exception) { announce(exception.message, 'error'); }
    };
    const resetPassword = async (account) => {
        const password = window.prompt(`Set a new password for ${account.name} (at least 8 characters):`);
        if (!password) return;
        if (password.length < 8) { announce('Use a password with at least 8 characters.', 'error'); return; }
        await change(account, 'password', password);
    };
    const changeEmail = async (account) => {
        const email = window.prompt(`Email address for ${account.name}:`, account.email);
        if (!email || email === account.email) return;
        await change(account, 'email', email);
    };
    return <>
        <div className="welcome-block"><div><h2>User accounts</h2><p>Create tenant and staff logins or adjust account access.</p></div><button className="button primary" onClick={() => setShowForm((value) => !value)}>Add account</button></div>
        {showForm && <Panel title="Create account" className="form-panel"><form className="form-grid" onSubmit={create}><Field label="Full name"><input name="name" required /></Field><Field label="Username (optional)"><input name="username" maxLength="50" /></Field><Field label="Email"><input type="email" name="email" required /></Field><Field label="Phone"><input name="phone" /></Field><Field label="Role"><select name="role"><option value="tenant">Tenant</option><option value="staff">Staff</option><option value="landlord">Landlord</option><option value="admin">Admin</option></select></Field><Field label="Temporary password"><input type="password" name="password" minLength="8" required /></Field><div className="form-actions"><button className="button primary">Create account</button><button type="button" className="button secondary" onClick={() => setShowForm(false)}>Cancel</button></div></form></Panel>}
        {error ? <ErrorState error={error} retry={load} /> : loading ? <Loading /> : <Panel title={`Accounts (${users.length})`}>{users.length ? <div className="table-scroll"><table><thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Phone</th><th>Role</th><th>Access</th><th>Actions</th></tr></thead><tbody>{users.map((account) => <tr key={account.id}><td>{account.name}</td><td>{account.username || '—'}</td><td>{account.email}</td><td>{account.phone || '—'}</td><td><select aria-label={`Role for ${account.name}`} value={account.role} onChange={(event) => change(account, 'role', event.target.value)}><option value="admin">Admin</option><option value="landlord">Landlord</option><option value="staff">Staff</option><option value="tenant">Tenant</option></select></td><td><button className={`status-button ${account.is_active ? 'enabled' : 'disabled'}`} onClick={() => change(account, 'is_active', !account.is_active)}>{account.is_active ? 'Active' : 'Disabled'}</button></td><td><div className="inline-actions"><button className="button secondary" onClick={() => changeEmail(account)}>Change email</button><button className="button secondary" onClick={() => resetPassword(account)}>Set password</button></div></td></tr>)}</tbody></table></div> : <Empty>No accounts found.</Empty>}</Panel>}
    </>;
}

function Activity() {
    const [logs, setLogs] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const load = useCallback(async () => {
        setLoading(true); setError('');
        try { setLogs(await fetchAll('/admin/activity-logs')); }
        catch (exception) { setError(exception.message); }
        finally { setLoading(false); }
    }, []);
    useEffect(() => { load(); }, [load]);
    return <Panel title="Recent activity">{error ? <ErrorState error={error} retry={load} /> : loading ? <Loading /> : logs.length ? <div className="table-scroll"><table><thead><tr><th>When</th><th>Account</th><th>Action</th><th>Record</th><th>Details</th></tr></thead><tbody>{logs.map((log) => <tr key={log.id}><td>{new Date(log.created_at).toLocaleString()}</td><td>{log.user?.name || 'System'}</td><td>{log.action}</td><td>{log.subject_type ? `${log.subject_type.split('\\').pop()} #${log.subject_id}` : '—'}</td><td>{log.properties ? JSON.stringify(log.properties) : '—'}</td></tr>)}</tbody></table></div> : <Empty>No activity has been recorded.</Empty>}</Panel>;
}

createRoot(document.getElementById('app')).render(<App />);
