import 'package:flutter/material.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../stations/station_selection.dart';
import 'kds_type.dart';

/// Sex tanlash: oshpaz shu ekranda ko'rsatiladigan bir yoki bir nechta sexni belgilaydi. "Umumiy" — sexsiz qatorlar
/// (onlayn buyurtmalar, yo'naltirish topilmagan taomlar). Tanlov qurilmada saqlanadi.
class StationPicker extends StatefulWidget {
  const StationPicker({
    super.key,
    required this.stations,
    required this.initial,
    required this.onConfirm,
    this.onCancel,
  });

  final List<KitchenStation> stations;
  final StationSelection initial;

  /// Tanlov tasdiqlandi.
  final void Function(Set<int> stationIds, bool general) onConfirm;

  /// Berilsa "Bekor" tugmasi bor (avvalgi tanlov amalda): birinchi marta tanlashda yo'q.
  final VoidCallback? onCancel;

  @override
  State<StationPicker> createState() => _StationPickerState();
}

class _StationPickerState extends State<StationPicker> {
  late final Set<int> _ids = <int>{
    for (final KitchenStation s in widget.stations)
      if (widget.initial.stationIds.contains(s.id)) s.id,
  };
  late bool _general = widget.initial.chosen && widget.initial.general;

  bool get _any => _ids.isNotEmpty || _general;

  bool get _all => _general && _ids.length == widget.stations.length;

  void _toggleAll() => setState(() {
    if (_all) {
      _ids.clear();
      _general = false;
    } else {
      _ids.addAll(widget.stations.map((KitchenStation s) => s.id));
      _general = true;
    }
  });

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 880),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text(
                "Qaysi sexni ko'rsatamiz?",
                style: RzType.display.copyWith(color: c.fg),
              ),
              const SizedBox(height: 4),
              Text(
                "Shu ekranda faqat tanlangan sexlarning taomlari chiqadi. Bir nechtasini tanlash mumkin; keyin yuqoridagi sex nomidan o'zgartirasiz.",
                style: RzType.body.copyWith(color: c.fgMuted),
              ),
              const SizedBox(height: 20),
              Wrap(
                spacing: 12,
                runSpacing: 12,
                children: <Widget>[
                  for (final KitchenStation s in widget.stations)
                    _StationTile(
                      key: ValueKey<String>('station-${s.id}'),
                      title: s.name,
                      subtitle: s.kindLabel.isEmpty ? null : s.kindLabel,
                      icon: _iconOf(s.kind),
                      selected: _ids.contains(s.id),
                      onTap: () => setState(() {
                        if (!_ids.add(s.id)) _ids.remove(s.id);
                      }),
                    ),
                  _StationTile(
                    key: const ValueKey<String>('station-general'),
                    title: 'Umumiy',
                    subtitle: 'Sexsiz taomlar, onlayn buyurtmalar',
                    icon: Icons.layers_outlined,
                    selected: _general,
                    onTap: () => setState(() => _general = !_general),
                  ),
                ],
              ),
              const SizedBox(height: 20),
              Wrap(
                spacing: 12,
                runSpacing: 12,
                alignment: WrapAlignment.spaceBetween,
                children: <Widget>[
                  RzButton(
                    label: _all ? 'Hammasini olib tashlash' : 'Hammasi',
                    icon: Icons.select_all_rounded,
                    variant: RzButtonVariant.secondary,
                    onPressed: _toggleAll,
                  ),
                  Wrap(
                    spacing: 12,
                    children: <Widget>[
                      if (widget.onCancel != null)
                        RzButton(
                          label: 'Bekor',
                          variant: RzButtonVariant.secondary,
                          onPressed: widget.onCancel,
                        ),
                      RzButton(
                        label: 'Davom etish',
                        icon: Icons.check_rounded,
                        size: RzButtonSize.lg,
                        onPressed: _any
                            ? () =>
                                  widget.onConfirm(Set<int>.of(_ids), _general)
                            : null,
                      ),
                    ],
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  static IconData _iconOf(String kind) => switch (kind) {
    StationKind.osh => Icons.rice_bowl_outlined,
    StationKind.mangal => Icons.local_fire_department_outlined,
    StationKind.saladBar => Icons.eco_outlined,
    StationKind.tea => Icons.emoji_food_beverage_outlined,
    StationKind.kitchen => Icons.soup_kitchen_outlined,
    _ => Icons.restaurant_outlined,
  };
}

class _StationTile extends StatelessWidget {
  const _StationTile({
    super.key,
    required this.title,
    required this.icon,
    required this.selected,
    required this.onTap,
    this.subtitle,
  });

  final String title;
  final String? subtitle;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return Semantics(
      button: true,
      selected: selected,
      label: '$title${selected ? ', tanlangan' : ''}',
      excludeSemantics: true,
      child: Material(
        color: selected ? c.brandSoft : c.surface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(RzRadius.card),
          side: BorderSide(
            color: selected ? c.brand : c.line,
            width: selected ? 3 : 1,
          ),
        ),
        child: InkWell(
          borderRadius: BorderRadius.circular(RzRadius.card),
          onTap: onTap,
          child: ConstrainedBox(
            constraints: const BoxConstraints(
              minWidth: 250,
              maxWidth: 400,
              minHeight: 88,
            ),
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: <Widget>[
                  Icon(
                    icon,
                    size: 36,
                    color: selected ? c.brandSoftFg : c.fgMuted,
                  ),
                  const SizedBox(width: 12),
                  Flexible(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        Text(title, style: KdsType.line.copyWith(color: c.fg)),
                        if (subtitle != null)
                          Text(
                            subtitle!,
                            style: RzType.footnote.copyWith(color: c.fgMuted),
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 12),
                  Icon(
                    selected
                        ? Icons.check_box_rounded
                        : Icons.check_box_outline_blank_rounded,
                    size: 32,
                    color: selected ? c.brand : c.fgMuted,
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
