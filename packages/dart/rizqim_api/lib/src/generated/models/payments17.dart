// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'form.dart';

part 'payments17.g.dart';

@JsonSerializable()
class Payments17 {
  const Payments17({
    required this.form,
    required this.amount,
    required this.prepayment,
    required this.credit,
    required this.cardType,
    required this.transactionId,
  });

  factory Payments17.fromJson(Map<String, Object?> json) =>
      _$Payments17FromJson(json);

  final Form form;
  final String amount;
  final bool prepayment;
  final bool credit;
  @JsonKey(name: 'card_type')
  final String? cardType;
  @JsonKey(name: 'transaction_id')
  final String? transactionId;

  Map<String, Object?> toJson() => _$Payments17ToJson(this);
}
