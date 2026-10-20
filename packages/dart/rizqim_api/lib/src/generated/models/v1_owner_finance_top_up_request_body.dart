// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'provider.dart';

part 'v1_owner_finance_top_up_request_body.g.dart';

@JsonSerializable()
class V1OwnerFinanceTopUpRequestBody {
  const V1OwnerFinanceTopUpRequestBody({
    required this.amount,
    required this.provider,
  });

  factory V1OwnerFinanceTopUpRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerFinanceTopUpRequestBodyFromJson(json);

  final num amount;
  final Provider provider;

  Map<String, Object?> toJson() => _$V1OwnerFinanceTopUpRequestBodyToJson(this);
}
