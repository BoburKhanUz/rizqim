// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'device3.dart';

part 'v1_auth_phone_select_account_request_body.g.dart';

@JsonSerializable()
class V1AuthPhoneSelectAccountRequestBody {
  const V1AuthPhoneSelectAccountRequestBody({
    required this.device,
    required this.selectionToken,
    required this.userId,
  });

  factory V1AuthPhoneSelectAccountRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1AuthPhoneSelectAccountRequestBodyFromJson(json);

  final Device3 device;
  @JsonKey(name: 'selection_token')
  final String selectionToken;
  @JsonKey(name: 'user_id')
  final int userId;

  Map<String, Object?> toJson() =>
      _$V1AuthPhoneSelectAccountRequestBodyToJson(this);
}
