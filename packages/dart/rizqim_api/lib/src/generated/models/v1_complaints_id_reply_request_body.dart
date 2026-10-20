// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_complaints_id_reply_request_body.g.dart';

@JsonSerializable()
class V1ComplaintsIdReplyRequestBody {
  const V1ComplaintsIdReplyRequestBody({required this.reply});

  factory V1ComplaintsIdReplyRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1ComplaintsIdReplyRequestBodyFromJson(json);

  final String reply;

  Map<String, Object?> toJson() => _$V1ComplaintsIdReplyRequestBodyToJson(this);
}
