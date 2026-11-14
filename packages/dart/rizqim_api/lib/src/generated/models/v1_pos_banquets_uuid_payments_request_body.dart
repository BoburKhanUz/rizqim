// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'rows9.dart';

part 'v1_pos_banquets_uuid_payments_request_body.g.dart';

@JsonSerializable()
class V1PosBanquetsUuidPaymentsRequestBody {
  const V1PosBanquetsUuidPaymentsRequestBody({
    required this.rows,
    this.version,
  });

  factory V1PosBanquetsUuidPaymentsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosBanquetsUuidPaymentsRequestBodyFromJson(json);

  final List<Rows9> rows;
  final int? version;

  Map<String, Object?> toJson() =>
      _$V1PosBanquetsUuidPaymentsRequestBodyToJson(this);
}
