// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_pos_vouchers_uuid_block_request_body.g.dart';

@JsonSerializable()
class V1OwnerPosVouchersUuidBlockRequestBody {
  const V1OwnerPosVouchersUuidBlockRequestBody({required this.reason});

  factory V1OwnerPosVouchersUuidBlockRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerPosVouchersUuidBlockRequestBodyFromJson(json);

  final String reason;

  Map<String, Object?> toJson() =>
      _$V1OwnerPosVouchersUuidBlockRequestBodyToJson(this);
}
