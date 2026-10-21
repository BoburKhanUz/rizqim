// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'delivery3.g.dart';

@JsonSerializable()
class Delivery3 {
  const Delivery3({
    required this.address,
    required this.latitude,
    required this.longitude,
    required this.zoneName,
  });

  factory Delivery3.fromJson(Map<String, Object?> json) =>
      _$Delivery3FromJson(json);

  final String? address;
  final num? latitude;
  final num? longitude;
  @JsonKey(name: 'zone_name')
  final String? zoneName;

  Map<String, Object?> toJson() => _$Delivery3ToJson(this);
}
