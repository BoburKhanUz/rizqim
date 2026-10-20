// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'couriers.g.dart';

@JsonSerializable()
class Couriers {
  const Couriers({
    required this.id,
    required this.name,
    required this.deliveredCount,
  });

  factory Couriers.fromJson(Map<String, Object?> json) =>
      _$CouriersFromJson(json);

  final int id;
  final String name;
  @JsonKey(name: 'delivered_count')
  final int deliveredCount;

  Map<String, Object?> toJson() => _$CouriersToJson(this);
}
