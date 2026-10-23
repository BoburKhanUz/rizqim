// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_bookings_uuid_no_show_request_body.g.dart';

@JsonSerializable()
class V1PosBookingsUuidNoShowRequestBody {
  const V1PosBookingsUuidNoShowRequestBody({this.version});

  factory V1PosBookingsUuidNoShowRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosBookingsUuidNoShowRequestBodyFromJson(json);

  final int? version;

  Map<String, Object?> toJson() =>
      _$V1PosBookingsUuidNoShowRequestBodyToJson(this);
}
