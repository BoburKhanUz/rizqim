// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'reason3.dart';

part 'v1_owner_batches_id_write_off_request_body.g.dart';

@JsonSerializable()
class V1OwnerBatchesIdWriteOffRequestBody {
  const V1OwnerBatchesIdWriteOffRequestBody({
    required this.quantity,
    required this.reason,
    this.note,
    this.close,
  });

  factory V1OwnerBatchesIdWriteOffRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerBatchesIdWriteOffRequestBodyFromJson(json);

  final num quantity;
  final Reason3 reason;
  final String? note;
  final bool? close;

  Map<String, Object?> toJson() =>
      _$V1OwnerBatchesIdWriteOffRequestBodyToJson(this);
}
