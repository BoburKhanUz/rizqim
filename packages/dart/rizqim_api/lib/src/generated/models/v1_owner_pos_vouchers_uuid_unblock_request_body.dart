// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_pos_vouchers_uuid_unblock_request_body.g.dart';

@JsonSerializable()
class V1OwnerPosVouchersUuidUnblockRequestBody {
  const V1OwnerPosVouchersUuidUnblockRequestBody({this.reason});

  factory V1OwnerPosVouchersUuidUnblockRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerPosVouchersUuidUnblockRequestBodyFromJson(json);

  final String? reason;

  Map<String, Object?> toJson() =>
      _$V1OwnerPosVouchersUuidUnblockRequestBodyToJson(this);
}
