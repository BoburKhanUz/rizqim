// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'items7.dart';

part 'data146.g.dart';

@JsonSerializable()
class Data146 {
  const Data146({
    required this.id,
    required this.uuid,
    required this.name,
    required this.perPersonPrice,
    required this.minimumGuests,
    required this.included,
    required this.description,
    required this.isActive,
    required this.sortOrder,
    required this.items,
  });

  factory Data146.fromJson(Map<String, Object?> json) =>
      _$Data146FromJson(json);

  final int id;
  final String uuid;
  final String name;
  @JsonKey(name: 'per_person_price')
  final String perPersonPrice;
  @JsonKey(name: 'minimum_guests')
  final int minimumGuests;
  final List<String> included;
  final String? description;
  @JsonKey(name: 'is_active')
  final bool isActive;
  @JsonKey(name: 'sort_order')
  final int sortOrder;
  final List<Items7> items;

  Map<String, Object?> toJson() => _$Data146ToJson(this);
}
