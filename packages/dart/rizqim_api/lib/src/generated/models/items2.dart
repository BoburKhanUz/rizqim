// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'combo.dart';

part 'items2.g.dart';

@JsonSerializable()
class Items2 {
  const Items2({
    required this.variantId,
    required this.quantity,
    this.modifiers,
    this.combo,
  });

  factory Items2.fromJson(Map<String, Object?> json) => _$Items2FromJson(json);

  @JsonKey(name: 'variant_id')
  final String variantId;

  /// P1-21: a fraction for items sold by weight (0.5 kg, at most 3 decimals); pieces stay whole (checked per unit)
  final num quantity;
  final List<String>? modifiers;

  /// P6-31: a combo's picks ({option_id, quantity?, modifiers?}); a required slot left out takes its default
  final List<Combo>? combo;

  Map<String, Object?> toJson() => _$Items2ToJson(this);
}
