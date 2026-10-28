// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'invoice.g.dart';

@JsonSerializable()
class Invoice {
  const Invoice({
    required this.uuid,
    required this.number,
    required this.amount,
    required this.outstanding,
    required this.dueDate,
  });

  factory Invoice.fromJson(Map<String, Object?> json) =>
      _$InvoiceFromJson(json);

  final String uuid;
  final String number;
  final String amount;
  final String outstanding;
  @JsonKey(name: 'due_date')
  final String dueDate;

  Map<String, Object?> toJson() => _$InvoiceToJson(this);
}
