// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'receipt6.dart';

part 'fiscal9.g.dart';

@JsonSerializable()
class Fiscal9 {
  const Fiscal9({
    required this.status,
    required this.receiptUuid,
    required this.error,
    required this.receipt,
  });

  factory Fiscal9.fromJson(Map<String, Object?> json) =>
      _$Fiscal9FromJson(json);

  final String? status;
  @JsonKey(name: 'receipt_uuid')
  final String? receiptUuid;
  final String? error;
  final Receipt6? receipt;

  Map<String, Object?> toJson() => _$Fiscal9ToJson(this);
}
