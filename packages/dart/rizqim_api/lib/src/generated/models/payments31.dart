// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'form.dart';

part 'payments31.g.dart';

@JsonSerializable()
class Payments31 {
  const Payments31({
    required this.form,
    required this.amount,
    required this.prepayment,
    required this.credit,
    required this.cardType,
    required this.transactionId,
    required this.cardLast4,
  });

  factory Payments31.fromJson(Map<String, Object?> json) =>
      _$Payments31FromJson(json);

  final Form form;
  final String amount;
  final bool prepayment;
  final bool credit;
  @JsonKey(name: 'card_type')
  final String? cardType;
  @JsonKey(name: 'transaction_id')
  final String? transactionId;
  @JsonKey(name: 'card_last4')
  final String? cardLast4;

  Map<String, Object?> toJson() => _$Payments31ToJson(this);
}
