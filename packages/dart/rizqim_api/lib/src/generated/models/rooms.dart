// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'bookings2.dart';

part 'rooms.g.dart';

@JsonSerializable()
class Rooms {
  const Rooms({
    required this.id,
    required this.name,
    required this.kind,
    required this.seats,
    required this.hallId,
    required this.hallName,
    required this.isActive,
    required this.minSpend,
    required this.bookings,
  });

  factory Rooms.fromJson(Map<String, Object?> json) => _$RoomsFromJson(json);

  final int id;
  final String name;
  final String kind;
  final int? seats;
  @JsonKey(name: 'hall_id')
  final int hallId;
  @JsonKey(name: 'hall_name')
  final String? hallName;
  @JsonKey(name: 'is_active')
  final bool isActive;
  @JsonKey(name: 'min_spend')
  final String? minSpend;
  final List<Bookings2> bookings;

  Map<String, Object?> toJson() => _$RoomsToJson(this);
}
