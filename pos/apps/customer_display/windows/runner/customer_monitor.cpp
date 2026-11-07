#include "customer_monitor.h"

#include <windows.h>

namespace {

// What the window was asked for at start, and whether it is full-screen now.
CustomerScreenOptions g_options;
bool g_placed = false;
bool g_fullscreen = false;

BOOL CALLBACK CollectMonitor(HMONITOR monitor, HDC, LPRECT, LPARAM data) {
  MONITORINFO info{};
  info.cbSize = sizeof(info);
  if (::GetMonitorInfoW(monitor, &info)) {
    MonitorBounds bounds;
    bounds.left = info.rcMonitor.left;
    bounds.top = info.rcMonitor.top;
    bounds.right = info.rcMonitor.right;
    bounds.bottom = info.rcMonitor.bottom;
    bounds.primary = (info.dwFlags & MONITORINFOF_PRIMARY) != 0;
    reinterpret_cast<std::vector<MonitorBounds>*>(data)->push_back(bounds);
  }
  return TRUE;
}

std::vector<MonitorBounds> ListMonitors() {
  std::vector<MonitorBounds> monitors;
  ::EnumDisplayMonitors(nullptr, nullptr, CollectMonitor,
                        reinterpret_cast<LPARAM>(&monitors));
  return SortMonitors(monitors);
}

// Moves and sizes in physical pixels (the runner is per-monitor DPI aware).
// Twice: crossing to a monitor with another scale sends WM_DPICHANGED, whose
// handler resizes the window to Windows' suggestion; the second call lands
// on the same monitor and keeps the exact rectangle.
void SetBounds(HWND window, long left, long top, long width, long height) {
  for (int i = 0; i < 2; ++i) {
    ::SetWindowPos(window, HWND_TOP, left, top, width, height,
                   SWP_FRAMECHANGED | SWP_NOACTIVATE | SWP_NOOWNERZORDER);
  }
}

void MakeFullscreen(HWND window, const MonitorBounds& monitor) {
  LONG_PTR style = ::GetWindowLongPtr(window, GWL_STYLE);
  style &= ~static_cast<LONG_PTR>(WS_OVERLAPPEDWINDOW);
  style |= WS_POPUP;
  ::SetWindowLongPtr(window, GWL_STYLE, style);
  SetBounds(window, monitor.left, monitor.top, monitor.right - monitor.left,
            monitor.bottom - monitor.top);
  g_fullscreen = true;
}

// Back to a normal window on the primary monitor (its monitor was unplugged).
void MakeWindowed(HWND window) {
  LONG_PTR style = ::GetWindowLongPtr(window, GWL_STYLE);
  style &= ~static_cast<LONG_PTR>(WS_POPUP);
  style |= WS_OVERLAPPEDWINDOW;
  ::SetWindowLongPtr(window, GWL_STYLE, style);
  RECT work{};
  ::SystemParametersInfoW(SPI_GETWORKAREA, 0, &work, 0);
  const long width = std::min<long>(1280, work.right - work.left);
  const long height = std::min<long>(720, work.bottom - work.top);
  SetBounds(window, work.left + 10, work.top + 10, width, height);
  g_fullscreen = false;
}

bool Place(HWND window) {
  const std::vector<MonitorBounds> monitors = ListMonitors();
  const int index = ChooseCustomerMonitor(monitors, g_options);
  if (index < 0) {
    if (g_fullscreen) MakeWindowed(window);
    return false;
  }
  MakeFullscreen(window, monitors[static_cast<size_t>(index)]);
  return true;
}

}  // namespace

bool PlaceCustomerScreen(CustomerWindowHandle window,
                         const CustomerScreenOptions& options) {
  HWND hwnd = static_cast<HWND>(window);
  if (hwnd == nullptr) return false;
  g_options = options;
  g_placed = true;
  return Place(hwnd);
}

bool CustomerScreenIsFullscreen() { return g_fullscreen; }

void OnCustomerScreenDisplayChange(CustomerWindowHandle window) {
  HWND hwnd = static_cast<HWND>(window);
  if (hwnd == nullptr || !g_placed) return;
  Place(hwnd);
}
