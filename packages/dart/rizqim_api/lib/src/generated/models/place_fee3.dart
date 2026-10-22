// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'place_fee3.g.dart';

@JsonSerializable()
class PlaceFee3 {
  const PlaceFee3({this.name, this.mxik, this.packageCode, this.unit});

  factory PlaceFee3.fromJson(Map<String, Object?> json) =>
      _$PlaceFee3FromJson(json);

  final String? name;
  final String? mxik;
  @JsonKey(name: 'package_code')
  final String? packageCode;
  final String? unit;

  Map<String, Object?> toJson() => _$PlaceFee3ToJson(this);
}
