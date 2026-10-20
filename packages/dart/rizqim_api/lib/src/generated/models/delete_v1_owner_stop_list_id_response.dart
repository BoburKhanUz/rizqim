// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'delete_v1_owner_stop_list_id_response.g.dart';

@JsonSerializable()
class DeleteV1OwnerStopListIdResponse {
  const DeleteV1OwnerStopListIdResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory DeleteV1OwnerStopListIdResponse.fromJson(Map<String, Object?> json) =>
      _$DeleteV1OwnerStopListIdResponseFromJson(json);

  final bool success;
  final String message;
  final dynamic data;

  Map<String, Object?> toJson() =>
      _$DeleteV1OwnerStopListIdResponseToJson(this);
}
