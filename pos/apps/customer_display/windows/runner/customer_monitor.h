#ifndef RUNNER_CUSTOMER_MONITOR_H_
#define RUNNER_CUSTOMER_MONITOR_H_

// P6 Windows: the customer display opens full-screen (borderless) on the
// till's second monitor when one exists, so the cashier keeps the main one.
// The choice below is plain C++ (no Win32 types) and is checked by a small
// host test; customer_monitor.cpp does the Win32 part.
//
// Command line (also in a shortcut's "Target"):
//   --monitor=N   full-screen on monitor N (1 = leftmost; ordered left to
//                 right, then top to bottom)
//   --fullscreen  full-screen even with a single monitor (a PC that only
//                 drives the customer screen or a TV)
//   --windowed    never full-screen (testing on the cashier's own screen)
// Default: the first monitor that is not the primary one, else a window.

#include <algorithm>
#include <cstdlib>
#include <string>
#include <vector>

struct CustomerScreenOptions {
  bool windowed = false;
  bool fullscreen = false;
  // 1-based; 0 = choose automatically.
  int monitor = 0;
};

struct MonitorBounds {
  long left = 0;
  long top = 0;
  long right = 0;
  long bottom = 0;
  bool primary = false;
};

inline CustomerScreenOptions ParseCustomerScreenOptions(
    const std::vector<std::string>& args) {
  CustomerScreenOptions options;
  const std::string monitor_flag = "--monitor=";
  for (const std::string& arg : args) {
    if (arg == "--windowed") {
      options.windowed = true;
    } else if (arg == "--fullscreen") {
      options.fullscreen = true;
    } else if (arg.rfind(monitor_flag, 0) == 0) {
      const int n = std::atoi(arg.c_str() + monitor_flag.size());
      options.monitor = n > 0 ? n : 0;
    }
  }
  return options;
}

// Monitors in a stable order: left to right, then top to bottom.
inline std::vector<MonitorBounds> SortMonitors(
    std::vector<MonitorBounds> monitors) {
  std::stable_sort(monitors.begin(), monitors.end(),
                   [](const MonitorBounds& a, const MonitorBounds& b) {
                     if (a.left != b.left) return a.left < b.left;
                     return a.top < b.top;
                   });
  return monitors;
}

// The index (into |sorted|) of the monitor to fill, or -1 to stay a normal
// window.
inline int ChooseCustomerMonitor(const std::vector<MonitorBounds>& sorted,
                                 const CustomerScreenOptions& options) {
  if (options.windowed || sorted.empty()) return -1;
  if (options.monitor > 0) {
    // A monitor that is not connected (now): the automatic choice.
    if (options.monitor <= static_cast<int>(sorted.size())) {
      return options.monitor - 1;
    }
  }
  for (size_t i = 0; i < sorted.size(); ++i) {
    if (!sorted[i].primary) return static_cast<int>(i);
  }
  if (options.fullscreen) {
    for (size_t i = 0; i < sorted.size(); ++i) {
      if (sorted[i].primary) return static_cast<int>(i);
    }
    return 0;
  }
  return -1;
}

// Win32 (customer_monitor.cpp). |window| is the runner's top-level window,
// before it is shown.
using CustomerWindowHandle = void*;

// Remembers |options| and moves the window onto the chosen monitor
// full-screen; returns whether it did.
bool PlaceCustomerScreen(CustomerWindowHandle window,
                         const CustomerScreenOptions& options);

// WM_DISPLAYCHANGE: a monitor was plugged in or out, or a resolution changed.
// Places the window again (back to a normal window on the primary monitor
// when the chosen one is gone).
void OnCustomerScreenDisplayChange(CustomerWindowHandle window);

// Whether the window is full-screen on a monitor now: it is then shown
// without taking the focus from the cashier app (a keyboard-wedge scanner
// types into the focused window).
bool CustomerScreenIsFullscreen();

#endif  // RUNNER_CUSTOMER_MONITOR_H_
