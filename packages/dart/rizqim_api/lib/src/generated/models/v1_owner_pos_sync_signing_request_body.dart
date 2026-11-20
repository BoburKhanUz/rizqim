// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_pos_sync_signing_request_body.g.dart';

@JsonSerializable()
class V1OwnerPosSyncSigningRequestBody {
  const V1OwnerPosSyncSigningRequestBody({
    required this.enforce,
    this.confirmUnsigned,
  });

  factory V1OwnerPosSyncSigningRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerPosSyncSigningRequestBodyFromJson(json);

  final bool enforce;
  @JsonKey(name: 'confirm_unsigned')
  final bool? confirmUnsigned;

  Map<String, Object?> toJson() =>
      _$V1OwnerPosSyncSigningRequestBodyToJson(this);
}
