// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'place_fee.g.dart';

@JsonSerializable()
class PlaceFee {
  const PlaceFee({
    required this.name,
    required this.mxik,
    required this.packageCode,
    required this.unit,
  });

  factory PlaceFee.fromJson(Map<String, Object?> json) =>
      _$PlaceFeeFromJson(json);

  final String name;
  final String? mxik;
  @JsonKey(name: 'package_code')
  final String? packageCode;
  final String unit;

  Map<String, Object?> toJson() => _$PlaceFeeToJson(this);
}
