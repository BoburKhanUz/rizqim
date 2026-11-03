// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'receipt8.dart';

part 'fiscal11.g.dart';

@JsonSerializable()
class Fiscal11 {
  const Fiscal11({
    required this.status,
    required this.receiptUuid,
    required this.error,
    required this.receipt,
  });

  factory Fiscal11.fromJson(Map<String, Object?> json) =>
      _$Fiscal11FromJson(json);

  final String? status;
  @JsonKey(name: 'receipt_uuid')
  final String? receiptUuid;
  final String? error;
  final Receipt8? receipt;

  Map<String, Object?> toJson() => _$Fiscal11ToJson(this);
}
