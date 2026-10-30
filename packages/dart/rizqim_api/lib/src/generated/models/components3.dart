// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'components3.g.dart';

@JsonSerializable()
class Components3 {
  const Components3({
    required this.type,
    required this.label,
    this.amount,
    this.percent,
    this.per,
    this.mode,
    this.baseValue,
    this.eventTypes,
  });

  factory Components3.fromJson(Map<String, Object?> json) =>
      _$Components3FromJson(json);

  final String type;
  final String label;
  final String? amount;
  final String? percent;
  final String? per;
  final String? mode;

  /// The name has been replaced because it contains a keyword. Original name: `base`.
  @JsonKey(name: 'base')
  final String? baseValue;
  @JsonKey(name: 'event_types')
  final List<String>? eventTypes;

  Map<String, Object?> toJson() => _$Components3ToJson(this);
}
