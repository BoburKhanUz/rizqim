// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'delivery2.g.dart';

@JsonSerializable()
class Delivery2 {
  const Delivery2({
    required this.name,
    required this.mxik,
    required this.packageCode,
    required this.unit,
  });

  factory Delivery2.fromJson(Map<String, Object?> json) =>
      _$Delivery2FromJson(json);

  final String name;
  final String? mxik;
  @JsonKey(name: 'package_code')
  final String? packageCode;
  final String unit;

  Map<String, Object?> toJson() => _$Delivery2ToJson(this);
}
