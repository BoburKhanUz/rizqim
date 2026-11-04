// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_bills_uuid_print_request_request_body.g.dart';

@JsonSerializable()
class V1PosBillsUuidPrintRequestRequestBody {
  const V1PosBillsUuidPrintRequestRequestBody({this.approvalToken});

  factory V1PosBillsUuidPrintRequestRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosBillsUuidPrintRequestRequestBodyFromJson(json);

  @JsonKey(name: 'approval_token')
  final String? approvalToken;

  Map<String, Object?> toJson() =>
      _$V1PosBillsUuidPrintRequestRequestBodyToJson(this);
}
