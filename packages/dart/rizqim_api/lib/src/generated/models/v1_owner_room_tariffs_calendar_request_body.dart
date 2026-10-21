// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_room_tariffs_calendar_request_body.g.dart';

@JsonSerializable()
class V1OwnerRoomTariffsCalendarRequestBody {
  const V1OwnerRoomTariffsCalendarRequestBody({
    required this.branchId,
    required this.weekendDays,
    required this.holidays,
  });

  factory V1OwnerRoomTariffsCalendarRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerRoomTariffsCalendarRequestBodyFromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'weekend_days')
  final List<int> weekendDays;
  final List<DateTime> holidays;

  Map<String, Object?> toJson() =>
      _$V1OwnerRoomTariffsCalendarRequestBodyToJson(this);
}
