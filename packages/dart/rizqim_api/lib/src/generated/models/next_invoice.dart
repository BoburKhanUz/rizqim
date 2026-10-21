// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'lines5.dart';

part 'next_invoice.g.dart';

@JsonSerializable()
class NextInvoice {
  const NextInvoice({
    required this.period,
    required this.issuedOn,
    required this.trial,
    required this.amount,
    required this.lines,
  });

  factory NextInvoice.fromJson(Map<String, Object?> json) =>
      _$NextInvoiceFromJson(json);

  final String period;
  @JsonKey(name: 'issued_on')
  final String issuedOn;
  final bool trial;
  final num amount;
  final List<Lines5> lines;

  Map<String, Object?> toJson() => _$NextInvoiceToJson(this);
}
