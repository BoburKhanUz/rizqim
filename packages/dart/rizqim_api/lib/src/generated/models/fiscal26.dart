// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'receipt7.dart';

part 'fiscal26.g.dart';

@JsonSerializable()
class Fiscal26 {
  const Fiscal26({
    required this.status,
    required this.receiptUuid,
    required this.error,
    required this.receipt,
  });

  factory Fiscal26.fromJson(Map<String, Object?> json) =>
      _$Fiscal26FromJson(json);

  final String? status;
  @JsonKey(name: 'receipt_uuid')
  final String? receiptUuid;
  final String? error;
  final Receipt7? receipt;

  Map<String, Object?> toJson() => _$Fiscal26ToJson(this);
}
