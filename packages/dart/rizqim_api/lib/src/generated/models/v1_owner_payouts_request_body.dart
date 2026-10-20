// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_payouts_request_body.g.dart';

@JsonSerializable()
class V1OwnerPayoutsRequestBody {
  const V1OwnerPayoutsRequestBody({
    required this.amount,
    required this.cardNumber,
    this.notes,
  });

  factory V1OwnerPayoutsRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerPayoutsRequestBodyFromJson(json);

  final num amount;
  @JsonKey(name: 'card_number')
  final String cardNumber;
  final String? notes;

  Map<String, Object?> toJson() => _$V1OwnerPayoutsRequestBodyToJson(this);
}
