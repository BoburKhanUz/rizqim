// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'delivery9.g.dart';

@JsonSerializable()
class Delivery9 {
  const Delivery9({
    required this.address,
    required this.latitude,
    required this.longitude,
    required this.zoneName,
  });

  factory Delivery9.fromJson(Map<String, Object?> json) =>
      _$Delivery9FromJson(json);

  final String? address;
  final num? latitude;
  final num? longitude;
  @JsonKey(name: 'zone_name')
  final String? zoneName;

  Map<String, Object?> toJson() => _$Delivery9ToJson(this);
}
