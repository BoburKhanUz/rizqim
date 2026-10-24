// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'day_type.dart';

part 'room_fee4.g.dart';

@JsonSerializable()
class RoomFee4 {
  const RoomFee4({
    required this.amount,
    required this.dayType,
    required this.tariffId,
  });

  factory RoomFee4.fromJson(Map<String, Object?> json) =>
      _$RoomFee4FromJson(json);

  final String amount;
  @JsonKey(name: 'day_type')
  final DayType dayType;
  @JsonKey(name: 'tariff_id')
  final int tariffId;

  Map<String, Object?> toJson() => _$RoomFee4ToJson(this);
}
