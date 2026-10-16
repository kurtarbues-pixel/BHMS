import React, { useCallback, useEffect, useState } from 'react';
import { useAuth } from '../../context/AuthContext';
import { api, fetchAll, formatMoney } from '../../api/client';
import { Room, RoomFormData, RoomStatus } from '../../types';
import { StatusBadge } from '../../components/Common/StatusBadge';
import { Field } from '../../components/Common/Field';
import { Modal } from '../../components/Common/Modal';
import { LoadingState, EmptyState, ErrorState } from '../../components/Common/States';

interface RoomsPageProps {
  announce: (message: string, kind?: 'success' | 'error' | 'warning' | 'info') => void;
}

export const RoomsPage: React.FC<RoomsPageProps> = ({ announce }) => {
  const { user } = useAuth();
  const canManage = user?.role !== 'tenant';

  const [rooms, setRooms] = useState<Room[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [filter, setFilter] = useState<string>('all');

  // Modal / form states
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingRoom, setEditingRoom] = useState<Room | null>(null);
  const [inquiryRoom, setInquiryRoom] = useState<Room | null>(null);
  const [inquiryMessage, setInquiryMessage] = useState('');
  const [isSaving, setIsSaving] = useState(false);

  // Form inputs
  const [roomNumber, setRoomNumber] = useState('');
  const [title, setTitle] = useState('');
  const [type, setType] = useState('');
  const [location, setLocation] = useState('');
  const [price, setPrice] = useState<number | string>('');
  const [status, setStatus] = useState<RoomStatus>('available');
  const [amenities, setAmenities] = useState('');
  const [description, setDescription] = useState('');

  const loadRooms = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const data = await fetchAll<Room>('/rooms?per_page=50');
      setRooms(data);
    } catch (err: any) {
      setError(err.message || 'Failed to load rooms.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadRooms();
  }, [loadRooms]);

  const openAddModal = () => {
    setEditingRoom(null);
    setRoomNumber('');
    setTitle('');
    setType('');
    setLocation('');
    setPrice('');
    setStatus('available');
    setAmenities('');
    setDescription('');
    setIsModalOpen(true);
  };

  const openEditModal = (room: Room) => {
    setEditingRoom(room);
    setRoomNumber(room.room_number);
    setTitle(room.title);
    setType(room.type || '');
    setLocation(room.location);
    setPrice(room.price);
    setStatus(room.status);
    setAmenities((room.amenities || []).join(', '));
    setDescription(room.description || '');
    setIsModalOpen(true);
  };

  const handleSaveRoom = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSaving(true);

    const payload: RoomFormData = {
      room_number: roomNumber.trim(),
      title: title.trim(),
      type: type.trim() || null,
      description: description.trim() || null,
      location: location.trim(),
      price: Number(price),
      status: status,
      amenities: amenities
        .split(',')
        .map((x) => x.trim())
        .filter(Boolean),
    };

    try {
      if (editingRoom) {
        await api(`/rooms/${editingRoom.id}`, {
          method: 'PUT',
          body: payload,
        });
        announce('Room updated successfully.');
      } else {
        await api('/rooms', {
          method: 'POST',
          body: payload,
        });
        announce('Room added successfully.');
      }
      setIsModalOpen(false);
      await loadRooms();
    } catch (err: any) {
      announce(err.message || 'Could not save room.', 'error');
    } finally {
      setIsSaving(false);
    }
  };

  const toggleAvailability = async (room: Room, newStatus: RoomStatus) => {
    try {
      await api(`/rooms/${room.id}/availability`, {
        method: 'PATCH',
        body: { status: newStatus },
      });
      announce(`Room status set to ${newStatus}.`);
      await loadRooms();
    } catch (err: any) {
      announce(err.message || 'Failed to update status.', 'error');
    }
  };

  const deleteRoom = async (room: Room) => {
    if (!window.confirm(`Are you sure you want to delete Room ${room.room_number} (${room.title})?`)) {
      return;
    }

    try {
      await api(`/rooms/${room.id}`, { method: 'DELETE' });
      announce('Room deleted successfully.');
      await loadRooms();
    } catch (err: any) {
      announce(err.message || 'Could not delete room.', 'error');
    }
  };

  const openInquiryModal = (room: Room) => {
    setInquiryRoom(room);
    setInquiryMessage(`I am interested in renting Room ${room.room_number}. Please let me know the requirements and move-in details.`);
  };

  const handleSendInquiry = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!inquiryRoom) return;

    setIsSaving(true);
    try {
      await api(`/rooms/${inquiryRoom.id}/inquiries`, {
        method: 'POST',
        body: { message: inquiryMessage },
      });
      announce('Your rental request was sent successfully! The landlord will review it.');
      setInquiryRoom(null);
    } catch (err: any) {
      announce(err.message || 'Could not submit inquiry.', 'error');
    } finally {
      setIsSaving(false);
    }
  };

  const filteredRooms = rooms.filter((r) => {
    if (filter === 'all') return true;
    return r.status === filter;
  });

  return (
    <div className="rooms-page">
      <div className="welcome-block">
        <div>
          <h2>Room Inventory</h2>
          <p className="muted">Browse rooms, check pricing, amenities, and current occupancy status.</p>
        </div>
        <div className="actions-cluster">
          {canManage && (
            <button type="button" className="button primary" onClick={openAddModal}>
              + Add Room
            </button>
          )}
          <button type="button" className="button secondary" onClick={loadRooms}>
            Refresh
          </button>
        </div>
      </div>

      <div className="filter-bar">
        <span className="filter-label">Filter:</span>
        {['all', 'available', 'occupied', 'maintenance'].map((statusKey) => (
          <button
            key={statusKey}
            type="button"
            className={`filter-chip ${filter === statusKey ? 'active' : ''}`}
            onClick={() => setFilter(statusKey)}
          >
            {statusKey.charAt(0).toUpperCase() + statusKey.slice(1)}
            {statusKey === 'all'
              ? ` (${rooms.length})`
              : ` (${rooms.filter((r) => r.status === statusKey).length})`}
          </button>
        ))}
      </div>

      {loading ? (
        <LoadingState message="Loading room listings…" />
      ) : error ? (
        <ErrorState error={error} retry={loadRooms} />
      ) : filteredRooms.length === 0 ? (
        <EmptyState
          title="No rooms found"
          message={
            filter !== 'all'
              ? `There are no rooms currently matching "${filter}".`
              : 'No rooms have been added to the system yet.'
          }
          action={
            canManage ? (
              <button type="button" className="button primary" onClick={openAddModal}>
                Add First Room
              </button>
            ) : undefined
          }
        />
      ) : (
        <div className="room-grid">
          {filteredRooms.map((room) => (
            <article className="room-card" key={room.id}>
              <div className="room-card-top">
                <StatusBadge status={room.status} />
                <span className="room-number">Room {room.room_number}</span>
              </div>

              <h3>{room.title}</h3>
              <p className="muted">
                {room.type || 'Room'} · {room.location}
              </p>

              <strong className="room-price">
                {formatMoney(room.price)} <small>per month</small>
              </strong>

              {room.description && (
                <p className="room-desc">{room.description}</p>
              )}

              {room.amenities && room.amenities.length > 0 && (
                <div className="amenities-tags">
                  {room.amenities.map((item, index) => (
                    <span key={index} className="amenity-tag">
                      {item}
                    </span>
                  ))}
                </div>
              )}

              <div className="room-actions">
                {canManage ? (
                  <>
                    <button
                      type="button"
                      className="button secondary sm"
                      onClick={() => openEditModal(room)}
                    >
                      Edit
                    </button>
                    {room.status === 'maintenance' ? (
                      <button
                        type="button"
                        className="button secondary sm"
                        onClick={() => toggleAvailability(room, 'available')}
                      >
                        Set Available
                      </button>
                    ) : room.status === 'available' ? (
                      <button
                        type="button"
                        className="button secondary sm"
                        onClick={() => toggleAvailability(room, 'maintenance')}
                      >
                        Maintenance
                      </button>
                    ) : (
                      <span className="occupied-badge">Active Lease</span>
                    )}
                    <button
                      type="button"
                      className="button danger-outline sm"
                      onClick={() => deleteRoom(room)}
                    >
                      Delete
                    </button>
                  </>
                ) : room.status === 'available' ? (
                  <button
                    type="button"
                    className="button primary full"
                    onClick={() => openInquiryModal(room)}
                  >
                    Request This Room
                  </button>
                ) : (
                  <span className="muted not-available-text">
                    {room.status === 'occupied' ? 'Currently Occupied' : 'Under Maintenance'}
                  </span>
                )}
              </div>
            </article>
          ))}
        </div>
      )}

      {/* Add / Edit Room Modal */}
      <Modal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        title={editingRoom ? `Edit Room ${editingRoom.room_number}` : 'Add New Room'}
        maxWidth="600px"
      >
        <form className="form-grid" onSubmit={handleSaveRoom}>
          <Field label="Room number" required>
            <input
              type="text"
              value={roomNumber}
              onChange={(e) => setRoomNumber(e.target.value)}
              placeholder="e.g. 101"
              required
              maxLength={20}
            />
          </Field>

          <Field label="Room name / title" required>
            <input
              type="text"
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              placeholder="e.g. Deluxe Single Room"
              required
            />
          </Field>

          <Field label="Room type">
            <input
              type="text"
              value={type}
              onChange={(e) => setType(e.target.value)}
              placeholder="Single, Bedspace, Master, Studio…"
            />
          </Field>

          <Field label="Location / Wing / Floor" required>
            <input
              type="text"
              value={location}
              onChange={(e) => setLocation(e.target.value)}
              placeholder="e.g. 2nd Floor, East Wing"
              required
            />
          </Field>

          <Field label="Monthly rent (₱)" required>
            <input
              type="number"
              min="0.01"
              step="0.01"
              value={price}
              onChange={(e) => setPrice(e.target.value)}
              placeholder="5000.00"
              required
            />
          </Field>

          <Field label="Status" required>
            <select
              value={status}
              onChange={(e) => setStatus(e.target.value as RoomStatus)}
              disabled={editingRoom?.status === 'occupied'}
            >
              <option value="available">Available</option>
              <option value="maintenance">Maintenance</option>
              {editingRoom?.status === 'occupied' && (
                <option value="occupied">Occupied by active lease</option>
              )}
            </select>
          </Field>

          <Field
            label="Amenities (comma separated)"
            hint="Separate each amenity with a comma (e.g. Wi-Fi, Aircon, Own CR, Cabinet)"
          >
            <input
              type="text"
              value={amenities}
              onChange={(e) => setAmenities(e.target.value)}
              placeholder="Wi-Fi, Aircon, Own Bathroom"
            />
          </Field>

          <Field label="Description">
            <textarea
              rows={3}
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Provide additional details or policies about this room…"
            />
          </Field>

          <div className="form-actions">
            <button type="submit" className="button primary" disabled={isSaving}>
              {isSaving ? 'Saving…' : editingRoom ? 'Update Room' : 'Create Room'}
            </button>
            <button
              type="button"
              className="button secondary"
              onClick={() => setIsModalOpen(false)}
            >
              Cancel
            </button>
          </div>
        </form>
      </Modal>

      {/* Tenant Inquiry Modal */}
      <Modal
        isOpen={Boolean(inquiryRoom)}
        onClose={() => setInquiryRoom(null)}
        title={`Request Room ${inquiryRoom?.room_number}`}
        maxWidth="500px"
      >
        {inquiryRoom && (
          <form className="form-stack" onSubmit={handleSendInquiry}>
            <p className="muted">
              You are sending a rental request for <strong>{inquiryRoom.title}</strong> (
              {formatMoney(inquiryRoom.price)}/mo). The landlord will be notified and can approve your lease.
            </p>

            <Field label="Your message to the landlord" required>
              <textarea
                rows={4}
                value={inquiryMessage}
                onChange={(e) => setInquiryMessage(e.target.value)}
                placeholder="Include your expected move-in date and questions…"
                required
              />
            </Field>

            <div className="form-actions">
              <button type="submit" className="button primary" disabled={isSaving}>
                {isSaving ? 'Submitting…' : 'Submit Request'}
              </button>
              <button
                type="button"
                className="button secondary"
                onClick={() => setInquiryRoom(null)}
              >
                Cancel
              </button>
            </div>
          </form>
        )}
      </Modal>
    </div>
  );
};

