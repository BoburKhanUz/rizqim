// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'receipt8.dart';

part 'fiscal10.g.dart';

@JsonSerializable()
class Fiscal10 {
  const Fiscal10({
    required this.status,
    required this.receiptUuid,
    required this.error,
    required this.receipt,
  });

  factory Fiscal10.fromJson(Map<String, Object?> json) =>
      _$Fiscal10FromJson(json);

  final String? status;
  @JsonKey(name: 'receipt_uuid')
  final String? receiptUuid;
  final String? error;
  final Receipt8? receipt;

  Map<String, Object?> toJson() => _$Fiscal10ToJson(this);
}
