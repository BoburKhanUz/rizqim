// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data130.dart';

part 'get_v1_owner_pos_tips_response.g.dart';

@JsonSerializable()
class GetV1OwnerPosTipsResponse {
  const GetV1OwnerPosTipsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerPosTipsResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerPosTipsResponseFromJson(json);

  final bool success;
  final String message;
  final Data130 data;

  Map<String, Object?> toJson() => _$GetV1OwnerPosTipsResponseToJson(this);
}
