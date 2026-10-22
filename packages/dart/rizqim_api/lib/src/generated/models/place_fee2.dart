// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'place_fee2.g.dart';

@JsonSerializable()
class PlaceFee2 {
  const PlaceFee2({
    required this.name,
    required this.mxik,
    required this.packageCode,
    required this.unit,
  });

  factory PlaceFee2.fromJson(Map<String, Object?> json) =>
      _$PlaceFee2FromJson(json);

  final String name;
  final String? mxik;
  @JsonKey(name: 'package_code')
  final String? packageCode;
  final String unit;

  Map<String, Object?> toJson() => _$PlaceFee2ToJson(this);
}
