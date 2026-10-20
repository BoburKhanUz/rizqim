// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'post_broadcasting_auth_response.g.dart';

@JsonSerializable()
class PostBroadcastingAuthResponse {
  const PostBroadcastingAuthResponse({required this.auth, this.channelData});

  factory PostBroadcastingAuthResponse.fromJson(Map<String, Object?> json) =>
      _$PostBroadcastingAuthResponseFromJson(json);

  final String auth;

  /// Faqat presence kanallarida.
  @JsonKey(name: 'channel_data')
  final String? channelData;

  Map<String, Object?> toJson() => _$PostBroadcastingAuthResponseToJson(this);
}
