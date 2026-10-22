// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'alerts4.g.dart';

@JsonSerializable()
class Alerts4 {
  const Alerts4({
    required this.openRoomHours,
    required this.voidAmount,
    required this.discountPercent,
    required this.unacceptedOnlineMinutes,
  });

  factory Alerts4.fromJson(Map<String, Object?> json) =>
      _$Alerts4FromJson(json);

  @JsonKey(name: 'open_room_hours')
  final int? openRoomHours;
  @JsonKey(name: 'void_amount')
  final String? voidAmount;
  @JsonKey(name: 'discount_percent')
  final String? discountPercent;
  @JsonKey(name: 'unaccepted_online_minutes')
  final int? unacceptedOnlineMinutes;

  Map<String, Object?> toJson() => _$Alerts4ToJson(this);
}
