// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'guests2.g.dart';

@JsonSerializable()
class Guests2 {
  const Guests2({
    required this.guaranteed,
    required this.minimum,
    required this.actual,
    required this.baseValue,
    required this.reservePercent,
    required this.planned,
  });

  factory Guests2.fromJson(Map<String, Object?> json) =>
      _$Guests2FromJson(json);

  final int guaranteed;
  final int minimum;
  final int? actual;

  /// The name has been replaced because it contains a keyword. Original name: `base`.
  @JsonKey(name: 'base')
  final int baseValue;
  @JsonKey(name: 'reserve_percent')
  final String reservePercent;
  final int planned;

  Map<String, Object?> toJson() => _$Guests2ToJson(this);
}
