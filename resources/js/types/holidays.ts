export type HolidayLocation = {
    country_code: string;
    country_name: string;
    region_code: string;
    region_name: string;
    city_code: string;
    city_name: string;
};

export function formatHolidayLocation(location: HolidayLocation): string {
    return [location.city_name, location.region_name, location.country_name].filter(Boolean).join(', ');
}

export type Holiday = {
    start_date: string;
    end_date: string;
    name: string;
    type: 'public' | 'optional' | 'unknown';
    scope: 'national' | 'regional' | 'local';
};
