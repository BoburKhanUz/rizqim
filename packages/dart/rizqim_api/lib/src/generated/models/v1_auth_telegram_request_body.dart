// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'device7.dart';

part 'v1_auth_telegram_request_body.g.dart';

@JsonSerializable()
class V1AuthTelegramRequestBody {
  const V1AuthTelegramRequestBody({
    required this.initData,
    required this.botUsername,
    this.startParam,
    this.device,
  });

  factory V1AuthTelegramRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1AuthTelegramRequestBodyFromJson(json);

  final String initData;
  @JsonKey(name: 'bot_username')
  final String botUsername;
  @JsonKey(name: 'start_param')
  final String? startParam;
  final Device7? device;

  Map<String, Object?> toJson() => _$V1AuthTelegramRequestBodyToJson(this);
}
