// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data119.dart';

part 'get_v1_owner_pos_tips_entries_response.g.dart';

@JsonSerializable()
class GetV1OwnerPosTipsEntriesResponse {
  const GetV1OwnerPosTipsEntriesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerPosTipsEntriesResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1OwnerPosTipsEntriesResponseFromJson(json);

  final bool success;
  final String message;
  final Data119 data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerPosTipsEntriesResponseToJson(this);
}
