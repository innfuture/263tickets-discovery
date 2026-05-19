import L from 'leaflet';
import iconRetina from 'leaflet/dist/images/marker-icon-2x.png';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import iconShadow from 'leaflet/dist/images/marker-shadow.png';
import 'leaflet/dist/leaflet.css';
import { MapContainer, Marker, Popup, TileLayer } from 'react-leaflet';

L.Icon.Default.mergeOptions({
    iconUrl,
    iconRetinaUrl: iconRetina,
    shadowUrl: iconShadow,
});

export default function VenueMapImpl({
    lat,
    lng,
    title,
}: {
    lat: number;
    lng: number;
    title?: string;
}) {
    return (
        <MapContainer
            center={[lat, lng]}
            zoom={14}
            scrollWheelZoom={false}
            className="size-full"
        >
            <TileLayer
                attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
            />
            <Marker position={[lat, lng]}>
                {title ? <Popup>{title}</Popup> : null}
            </Marker>
        </MapContainer>
    );
}
