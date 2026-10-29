// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'ticket9.g.dart';

@JsonSerializable()
class Ticket9 {
  const Ticket9({
    required this.number,
    required this.businessDate,
    required this.orderLabel,
    required this.message,
  });

  factory Ticket9.fromJson(Map<String, Object?> json) =>
      _$Ticket9FromJson(json);

  final int number;
  @JsonKey(name: 'business_date')
  final String? businessDate;
  @JsonKey(name: 'order_label')
  final String orderLabel;
  final String message;

  Map<String, Object?> toJson() => _$Ticket9ToJson(this);
}
