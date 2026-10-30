// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data269.dart';

part 'get_v1_pos_tips_response.g.dart';

@JsonSerializable()
class GetV1PosTipsResponse {
  const GetV1PosTipsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosTipsResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1PosTipsResponseFromJson(json);

  final bool success;
  final String message;
  final Data269 data;

  Map<String, Object?> toJson() => _$GetV1PosTipsResponseToJson(this);
}
