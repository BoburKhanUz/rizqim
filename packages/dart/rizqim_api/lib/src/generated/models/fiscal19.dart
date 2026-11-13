// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'receipt12.dart';

part 'fiscal19.g.dart';

@JsonSerializable()
class Fiscal19 {
  const Fiscal19({
    required this.status,
    required this.receiptUuid,
    required this.error,
    required this.receipt,
  });

  factory Fiscal19.fromJson(Map<String, Object?> json) =>
      _$Fiscal19FromJson(json);

  final String? status;
  @JsonKey(name: 'receipt_uuid')
  final String? receiptUuid;
  final String? error;
  final Receipt12? receipt;

  Map<String, Object?> toJson() => _$Fiscal19ToJson(this);
}
