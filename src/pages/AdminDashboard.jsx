import { useEffect, useState } from "react";
import { Link, Navigate, Route, Routes, useLocation, useNavigate } from "react-router-dom";
import { api } from "../services/api";
const sections=[["Overview","/admin"],["Bookings","/admin/bookings"],["Vehicles","/admin/vehicles"],["Payments","/admin/payments"],["Driver earnings","/admin/earnings"],["Payouts","/admin/payouts"],["Refunds","/admin/refunds"],["Pricing","/admin/pricing"],["Customers","/admin/customers"]];

function BookingTable({rows,onStatusChange}) {
 if(!rows.length) return <p className="admin-empty">No booking records returned by the API yet.</p>;
 return <div className="admin-table-wrap"><table className="admin-table"><thead><tr><th>Reference</th><th>Customer</th><th>Journey</th><th>Pickup</th><th>Fare</th><th>Status</th>{onStatusChange&&<th>Action</th>}</tr></thead><tbody>
 {rows.map(row=><tr key={row.id}><td>{row.booking_reference||`#${row.id}`}</td><td>{row.customer_name||"—"}</td><td>{row.pickup_location} → {row.destination}</td><td>{row.pickup_at||"—"}</td><td>{row.currency||"NPR"} {row.total_fare??"—"}</td><td><span className={`admin-status ${String(row.status||"pending").toLowerCase()}`}>{row.status||"Pending"}</span></td>
 {onStatusChange&&<td><select value={row.status} onChange={e=>onStatusChange(row.id,e.target.value)} aria-label={`Change status for booking ${row.id}`}><option value="pending">Pending</option><option value="confirmed">Confirmed</option><option value="cancelled">Cancelled</option><option value="completed">Completed</option></select></td>}</tr>)}
 </tbody></table></div>;
}
function Overview({stats,bookings}) {
 const cards=[["Total bookings",stats.total_bookings??"—"],["Pending",stats.pending_bookings??"—"],["Confirmed",stats.confirmed_bookings??"—"],["Active vehicles",stats.active_vehicles??"—"]];
 return <section><div className="admin-page-heading"><div><p className="admin-eyebrow">OVERVIEW</p><h1>Dashboard</h1><p className="admin-muted">A live snapshot of your OneCar operation.</p></div></div>
 <div className="admin-stat-grid">{cards.map(([label,value])=><article className="admin-stat" key={label}><span>{label}</span><strong>{value}</strong></article>)}</div>
 <div className="admin-panel"><div className="admin-panel-heading"><h2>Recent bookings</h2><Link to="/admin/bookings">View all →</Link></div><BookingTable rows={bookings.slice(0,6)}/></div></section>;
}
function Bookings({bookings,refresh}) {
 const [query,setQuery]=useState("");const [message,setMessage]=useState("");
 const filtered=bookings.filter(b=>JSON.stringify(b).toLowerCase().includes(query.toLowerCase()));
 async function updateStatus(id,status){setMessage("");try{await api(`/admin/bookings/${id}/status`,{method:"PATCH",body:JSON.stringify({status})});await refresh();}catch(e){setMessage(e.message);}}
 return <section><div className="admin-page-heading"><div><p className="admin-eyebrow">RESERVATIONS</p><h1>Bookings</h1><p className="admin-muted">Review requests and update permitted booking statuses.</p></div></div>
 {message&&<div className="admin-error">{message}</div>}<div className="admin-panel"><input className="admin-search" placeholder="Search reference, customer or route…" value={query} onChange={e=>setQuery(e.target.value)}/><BookingTable rows={filtered} onStatusChange={updateStatus}/></div></section>;
}
function Vehicles({vehicles,refresh}) {
 const initial={name:"",category:"Comfort Sedan",passengers:3,luggage:2,base_fare:650,active:true};
 const [form,setForm]=useState(initial);const [message,setMessage]=useState("");
 async function addVehicle(e){e.preventDefault();setMessage("");try{await api("/admin/vehicles",{method:"POST",body:JSON.stringify(form)});setMessage("Vehicle added.");setForm(initial);await refresh();}catch(err){setMessage(err.message);}}
 return <section><div className="admin-page-heading"><div><p className="admin-eyebrow">FLEET MANAGEMENT</p><h1>Vehicles</h1><p className="admin-muted">Add cars to the fleet. The backend must validate and save them in MySQL.</p></div></div>
 {message&&<div className={message==="Vehicle added."?"admin-success":"admin-error"}>{message}</div>}
 <div className="admin-panel"><h2>Add a vehicle</h2><form className="admin-vehicle-form" onSubmit={addVehicle}>
 <label>Vehicle name<input value={form.name} onChange={e=>setForm({...form,name:e.target.value})} required placeholder="e.g. Toyota Corolla"/></label>
 <label>Category<select value={form.category} onChange={e=>setForm({...form,category:e.target.value})}><option>Comfort Sedan</option><option>Premium SUV</option><option>Executive Class</option></select></label>
 <label>Passengers<input type="number" min="1" max="20" value={form.passengers} onChange={e=>setForm({...form,passengers:Number(e.target.value)})} required/></label>
 <label>Luggage capacity<input type="number" min="0" max="30" value={form.luggage} onChange={e=>setForm({...form,luggage:Number(e.target.value)})} required/></label>
 <label>Base fare (NPR demo value)<input type="number" min="0" step="0.01" value={form.base_fare} onChange={e=>setForm({...form,base_fare:Number(e.target.value)})} required/></label>
 <button className="admin-primary">Save vehicle</button></form></div>
 <div className="admin-panel"><h2>Current vehicles</h2>{vehicles.length?<div className="admin-table-wrap"><table className="admin-table"><thead><tr><th>Name</th><th>Category</th><th>Passengers</th><th>Base fare</th><th>Status</th></tr></thead><tbody>{vehicles.map(v=><tr key={v.id}><td>{v.name}</td><td>{v.category}</td><td>{v.passengers??"—"}</td><td>NPR {v.base_fare??"—"}</td><td>{v.active?"Active":"Inactive"}</td></tr>)}</tbody></table></div>:<p className="admin-empty">No vehicles returned by the API yet.</p>}</div></section>;
}

function Payments({}) {
 const [rows,setRows]=useState([]),[error,setError]=useState(""),[loading,setLoading]=useState(true),[message,setMessage]=useState("");
 const load=()=>api("/admin/payments").then(r=>setRows(r.data||[])).catch(e=>setError(e.message)).finally(()=>setLoading(false));
 useEffect(()=>{load();},[]);
 async function refund(row){if(!window.confirm(`Simulate a full refund for ${row.booking_reference}? No real money will move.`))return;setError("");setMessage("");try{await api("/admin/refunds",{method:"POST",body:JSON.stringify({payment_id:row.id,reason:"Admin initiated demo refund"})});setMessage("Demo refund recorded. No money was returned.");await load();}catch(e){setError(e.message);}}
 return <section><div className="admin-page-heading"><div><p className="admin-eyebrow">FINANCE</p><h1>Payments</h1><p className="admin-muted">Demo transactions only. No real money is moved.</p></div></div>{error&&<div className="admin-error">{error}</div>}{message&&<div className="admin-success">{message}</div>}<div className="admin-panel"><h2>Payment ledger</h2>{loading?<p>Loading payments…</p>:rows.length?<div className="admin-table-wrap"><table className="admin-table"><thead><tr><th>Booking</th><th>Customer</th><th>Method</th><th>Amount</th><th>Status</th><th>Reference</th><th>Action</th></tr></thead><tbody>{rows.map(r=><tr key={r.id}><td>{r.booking_reference}</td><td>{r.customer_name}</td><td>{r.method}</td><td>{r.currency} {r.amount}</td><td><span className={`admin-status ${String(r.status).toLowerCase()}`}>{r.status}</span></td><td>{r.provider_reference||r.transaction_reference}</td><td>{r.status==="paid_demo"?<button className="admin-primary" onClick={()=>refund(r)}>Refund demo</button>:"—"}</td></tr>)}</tbody></table></div>:<p className="admin-empty">No payments yet. Create a booking on the customer page first.</p>}</div></section>;
}
function Earnings({}) {
 const [rows,setRows]=useState([]),[error,setError]=useState(""),[loading,setLoading]=useState(true);
 useEffect(()=>{api("/admin/earnings").then(r=>setRows(r.data||[])).catch(e=>setError(e.message)).finally(()=>setLoading(false));},[]);
 return <section><div className="admin-page-heading"><div><p className="admin-eyebrow">SETTLEMENTS</p><h1>Driver earnings</h1><p className="admin-muted">Commissions and driver shares are accounting simulations, not payouts.</p></div></div>{error&&<div className="admin-error">{error}</div>}<div className="admin-panel"><h2>Earnings by completed ride</h2>{loading?<p>Loading earnings…</p>:rows.length?<div className="admin-table-wrap"><table className="admin-table"><thead><tr><th>Booking</th><th>Vehicle</th><th>Gross fare</th><th>Platform commission</th><th>Driver net</th><th>Status</th></tr></thead><tbody>{rows.map(r=><tr key={r.id}><td>{r.booking_reference}</td><td>{r.vehicle_category}</td><td>NPR {r.gross_fare}</td><td>NPR {r.commission_amount}</td><td>NPR {r.net_earnings}</td><td>{r.status}</td></tr>)}</tbody></table></div>:<p className="admin-empty">Mark a booking completed to create a demo earnings entry.</p>}</div></section>;
}
function Payouts() {
 const [rows,setRows]=useState([]),[amount,setAmount]=useState(""),[notes,setNotes]=useState("Demo payout exercise"),[error,setError]=useState(""),[message,setMessage]=useState("");
 const load=()=>api("/admin/payouts").then(r=>setRows(r.data||[])).catch(e=>setError(e.message));
 useEffect(()=>{load();},[]);
 async function create(e){e.preventDefault();setError("");setMessage("");try{const r=await api("/admin/payouts",{method:"POST",body:JSON.stringify({amount:Number(amount),notes})});setMessage(`Recorded ${r.data?.payout_reference||"demo payout"}. No bank transfer was made.`);setAmount("");await load();}catch(e){setError(e.message);}}
 return <section><div className="admin-page-heading"><div><p className="admin-eyebrow">DRIVER SETTLEMENT</p><h1>Payout simulations</h1><p className="admin-muted">Creates demo records only; this does not send money to a bank.</p></div></div>{error&&<div className="admin-error">{error}</div>}{message&&<div className="admin-success">{message}</div>}<div className="admin-panel"><h2>Record a demo payout</h2><form className="admin-vehicle-form" onSubmit={create}><label>Amount (NPR)<input type="number" min="1" step="1" value={amount} onChange={e=>setAmount(e.target.value)} required/></label><label>Note<input value={notes} onChange={e=>setNotes(e.target.value)} required/></label><button className="admin-primary">Record payout simulation</button></form></div><div className="admin-panel"><h2>Payout history</h2>{rows.length?<div className="admin-table-wrap"><table className="admin-table"><thead><tr><th>Reference</th><th>Amount</th><th>Status</th><th>Notes</th><th>Created</th></tr></thead><tbody>{rows.map(r=><tr key={r.id}><td>{r.payout_reference}</td><td>{r.currency} {r.amount}</td><td>{r.status}</td><td>{r.notes}</td><td>{r.created_at}</td></tr>)}</tbody></table></div>:<p className="admin-empty">No payout simulations recorded yet.</p>}</div></section>;
}
function Refunds() {
 const [rows,setRows]=useState([]),[error,setError]=useState("");
 useEffect(()=>{api("/admin/refunds").then(r=>setRows(r.data||[])).catch(e=>setError(e.message));},[]);
 return <section><div className="admin-page-heading"><div><p className="admin-eyebrow">REFUND MANAGEMENT</p><h1>Refunds</h1><p className="admin-muted">All refunds in this project are simulated.</p></div></div>{error&&<div className="admin-error">{error}</div>}<div className="admin-panel">{rows.length?<div className="admin-table-wrap"><table className="admin-table"><thead><tr><th>Booking</th><th>Amount</th><th>Reason</th><th>Status</th><th>Created</th></tr></thead><tbody>{rows.map(r=><tr key={r.id}><td>{r.booking_reference}</td><td>NPR {r.amount}</td><td>{r.reason}</td><td>{r.status}</td><td>{r.created_at}</td></tr>)}</tbody></table></div>:<p className="admin-empty">No demo refunds yet. Use Refund demo in Payments for a successful simulated payment.</p>}</div></section>;
}
function Pricing() {
 const [rows,setRows]=useState([]),[error,setError]=useState(""),[message,setMessage]=useState("");
 useEffect(()=>{api("/admin/pricing").then(r=>setRows(r.data||[])).catch(e=>setError(e.message));},[]);
 function change(id,key,value){setRows(old=>old.map(r=>r.id===id?{...r,[key]:value}:r));}
 async function save(row){setError("");setMessage("");try{await api("/admin/pricing",{method:"PATCH",body:JSON.stringify(row)});setMessage(`Saved pricing for ${row.vehicle_category}.`);}catch(e){setError(e.message);}}
 return <section><div className="admin-page-heading"><div><p className="admin-eyebrow">FARE RULES</p><h1>Pricing</h1><p className="admin-muted">These values are used by the backend to calculate the final demo fare.</p></div></div>{error&&<div className="admin-error">{error}</div>}{message&&<div className="admin-success">{message}</div>}<div className="admin-panel"><div className="admin-table-wrap"><table className="admin-table"><thead><tr><th>Vehicle</th><th>Base fare</th><th>Per km</th><th>Airport add-on</th><th>Outstation add-on</th><th>Action</th></tr></thead><tbody>{rows.map(r=><tr key={r.id}><td>{r.vehicle_category}</td>{[["base_fare","Base fare"],["per_km","Per km"],["airport_surcharge","Airport"],["outstation_surcharge","Outstation"]].map(([key,label])=><td key={key}><input aria-label={`${r.vehicle_category} ${label}`} type="number" min="0" step="1" value={r[key]} onChange={e=>change(r.id,key,Number(e.target.value))} style={{width:95,padding:8,border:"1px solid #dfe5d9",borderRadius:8}}/></td>)}<td><button className="admin-primary" onClick={()=>save(r)}>Save</button></td></tr>)}</tbody></table></div></div></section>;
}
function Customers() {
 const [rows,setRows]=useState([]),[error,setError]=useState("");
 useEffect(()=>{api("/admin/customers").then(r=>setRows(r.data||[])).catch(e=>setError(e.message));},[]);
 return <section><div className="admin-page-heading"><div><p className="admin-eyebrow">CUSTOMER DIRECTORY</p><h1>Customers</h1><p className="admin-muted">Customer data from demo bookings.</p></div></div>{error&&<div className="admin-error">{error}</div>}<div className="admin-panel">{rows.length?<div className="admin-table-wrap"><table className="admin-table"><thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Created</th></tr></thead><tbody>{rows.map(r=><tr key={r.id}><td>{r.full_name}</td><td>{r.phone}</td><td>{r.email||"—"}</td><td>{r.created_at}</td></tr>)}</tbody></table></div>:<p className="admin-empty">No customer records yet.</p>}</div></section>;
}

export default function AdminDashboard(){
 const [authorized,setAuthorized]=useState(null),[stats,setStats]=useState({}),[bookings,setBookings]=useState([]),[vehicles,setVehicles]=useState([]),[error,setError]=useState("");
 const navigate=useNavigate(),location=useLocation();
 async function refresh(){
  const [s,b,v]=await Promise.all([api("/admin/dashboard"),api("/admin/bookings"),api("/admin/vehicles")]);
  setStats(s.data||s);setBookings(b.data||b.bookings||[]);setVehicles(v.data||v.vehicles||[]);
 }
 useEffect(()=>{let alive=true;api("/admin/me").then(()=>{if(!alive)return;setAuthorized(true);return refresh().catch(e=>setError(e.message));}).catch(()=>{if(alive)setAuthorized(false);});return()=>{alive=false;};},[]);
 async function logout(){try{await api("/admin/logout",{method:"POST",body:JSON.stringify({})});}catch{}navigate("/admin/login",{replace:true});}
 if(authorized===null)return <div className="admin-loading">Checking administrator access…</div>;
 if(!authorized)return <Navigate to="/admin/login" replace state={{from:location.pathname}}/>;
 const refreshSafe=()=>refresh().catch(e=>setError(e.message));
 return <div className="admin-layout"><aside className="admin-sidebar"><Link className="admin-brand" to="/">↗ onecar<span>.</span></Link><p className="admin-sidebar-label">WORKSPACE</p><nav>{sections.map(([name,path])=><Link key={path} className={location.pathname===path?"selected":""} to={path}>{name}</Link>)}</nav><div className="admin-sidebar-bottom"><span>Administrator</span><button onClick={logout}>Sign out</button><Link to="/">← View website</Link></div></aside>
 <main className="admin-main"><header className="admin-topbar"><span>OneCar / Admin</span><span>Secure management</span></header>{error&&<div className="admin-error">{error} — check the PHP API connection and route configuration.</div>}
 <Routes><Route index element={<Overview stats={stats} bookings={bookings}/>}/><Route path="bookings" element={<Bookings bookings={bookings} refresh={refreshSafe}/>}/><Route path="vehicles" element={<Vehicles vehicles={vehicles} refresh={refreshSafe}/>}/>
 <Route path="payments" element={<Payments/>}/><Route path="earnings" element={<Earnings/>}/><Route path="payouts" element={<Payouts/>}/><Route path="refunds" element={<Refunds/>}/><Route path="pricing" element={<Pricing/>}/><Route path="customers" element={<Customers/>}/>
 <Route path="*" element={<Navigate to="/admin" replace/>}/></Routes></main></div>;
}
