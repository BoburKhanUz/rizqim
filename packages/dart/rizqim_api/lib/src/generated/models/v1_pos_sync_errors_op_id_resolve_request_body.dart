// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'resolution.dart';

part 'v1_pos_sync_errors_op_id_resolve_request_body.g.dart';

@JsonSerializable()
class V1PosSyncErrorsOpIdResolveRequestBody {
  const V1PosSyncErrorsOpIdResolveRequestBody({
    required this.resolution,
    this.note,
  });

  factory V1PosSyncErrorsOpIdResolveRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosSyncErrorsOpIdResolveRequestBodyFromJson(json);

  final Resolution resolution;
  final String? note;

  Map<String, Object?> toJson() =>
      _$V1PosSyncErrorsOpIdResolveRequestBodyToJson(this);
}
