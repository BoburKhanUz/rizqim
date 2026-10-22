// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'variants2.g.dart';

@JsonSerializable()
class Variants2 {
  const Variants2({required this.id, required this.unitLabel});

  factory Variants2.fromJson(Map<String, Object?> json) =>
      _$Variants2FromJson(json);

  final int id;
  @JsonKey(name: 'unit_label')
  final String? unitLabel;

  Map<String, Object?> toJson() => _$Variants2ToJson(this);
}
