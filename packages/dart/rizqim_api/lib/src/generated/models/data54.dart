// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'subscription.dart';
import 'terminals.dart';
import 'next_invoice.dart';

part 'data54.g.dart';

@JsonSerializable()
class Data54 {
  const Data54({
    required this.subscription,
    required this.commissionRate,
    required this.terminals,
    required this.nextInvoice,
  });

  factory Data54.fromJson(Map<String, Object?> json) => _$Data54FromJson(json);

  final Subscription? subscription;
  @JsonKey(name: 'commission_rate')
  final num commissionRate;
  final Terminals terminals;
  @JsonKey(name: 'next_invoice')
  final NextInvoice? nextInvoice;

  Map<String, Object?> toJson() => _$Data54ToJson(this);
}
