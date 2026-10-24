// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'alerts.g.dart';

@JsonSerializable()
class Alerts {
  const Alerts({
    required this.openRoomHours,
    required this.voidAmount,
    required this.discountPercent,
    required this.unacceptedOnlineMinutes,
    required this.cashOutAmount,
  });

  factory Alerts.fromJson(Map<String, Object?> json) => _$AlertsFromJson(json);

  @JsonKey(name: 'open_room_hours')
  final int? openRoomHours;
  @JsonKey(name: 'void_amount')
  final String? voidAmount;
  @JsonKey(name: 'discount_percent')
  final String? discountPercent;
  @JsonKey(name: 'unaccepted_online_minutes')
  final int? unacceptedOnlineMinutes;
  @JsonKey(name: 'cash_out_amount')
  final String? cashOutAmount;

  Map<String, Object?> toJson() => _$AlertsToJson(this);
}
