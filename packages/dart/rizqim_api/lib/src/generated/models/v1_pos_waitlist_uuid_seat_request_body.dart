// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_waitlist_uuid_seat_request_body.g.dart';

@JsonSerializable()
class V1PosWaitlistUuidSeatRequestBody {
  const V1PosWaitlistUuidSeatRequestBody({
    required this.diningTableId,
    this.openOrder,
    this.waiterId,
    this.version,
  });

  factory V1PosWaitlistUuidSeatRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosWaitlistUuidSeatRequestBodyFromJson(json);

  @JsonKey(name: 'dining_table_id')
  final int diningTableId;
  @JsonKey(name: 'open_order')
  final bool? openOrder;
  @JsonKey(name: 'waiter_id')
  final int? waiterId;
  final int? version;

  Map<String, Object?> toJson() =>
      _$V1PosWaitlistUuidSeatRequestBodyToJson(this);
}
