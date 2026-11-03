// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'combo3.g.dart';

@JsonSerializable()
class Combo3 {
  const Combo3({required this.optionId, this.quantity, this.modifiers});

  factory Combo3.fromJson(Map<String, Object?> json) => _$Combo3FromJson(json);

  /// P6-31
  @JsonKey(name: 'option_id')
  final String optionId;
  final int? quantity;
  final List<String>? modifiers;

  Map<String, Object?> toJson() => _$Combo3ToJson(this);
}
