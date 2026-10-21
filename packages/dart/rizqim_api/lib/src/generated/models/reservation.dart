// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'reservation.g.dart';

@JsonSerializable()
class Reservation {
  const Reservation({
    required this.bookingUuid,
    required this.startsAt,
    required this.guests,
  });

  factory Reservation.fromJson(Map<String, Object?> json) =>
      _$ReservationFromJson(json);

  @JsonKey(name: 'booking_uuid')
  final String bookingUuid;
  @JsonKey(name: 'starts_at')
  final String startsAt;
  final int? guests;

  Map<String, Object?> toJson() => _$ReservationToJson(this);
}
