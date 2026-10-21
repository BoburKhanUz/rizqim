// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_bookings_uuid_arrive_request_body.g.dart';

@JsonSerializable()
class V1PosBookingsUuidArriveRequestBody {
  const V1PosBookingsUuidArriveRequestBody({
    this.guests,
    this.diningTableId,
    this.version,
  });

  factory V1PosBookingsUuidArriveRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosBookingsUuidArriveRequestBodyFromJson(json);

  final int? guests;
  @JsonKey(name: 'dining_table_id')
  final int? diningTableId;
  final int? version;

  Map<String, Object?> toJson() =>
      _$V1PosBookingsUuidArriveRequestBodyToJson(this);
}
