// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data116.dart';

part 'get_v1_owner_pos_tips_distributions_response.g.dart';

@JsonSerializable()
class GetV1OwnerPosTipsDistributionsResponse {
  const GetV1OwnerPosTipsDistributionsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerPosTipsDistributionsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1OwnerPosTipsDistributionsResponseFromJson(json);

  final bool success;
  final String message;
  final Data116 data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerPosTipsDistributionsResponseToJson(this);
}
