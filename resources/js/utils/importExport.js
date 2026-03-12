import api from '../api';

export async function downloadExport(url, filename) {
    const response = await api.get(url, { responseType: 'blob' });
    const blob = new Blob([response.data]);
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.click();
    URL.revokeObjectURL(link.href);
}

export async function uploadImport(url, file, format) {
    const form = new FormData();
    form.append('file', file);
    form.append('format', format);
    const { data } = await api.post(url, form, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
    return data;
}
