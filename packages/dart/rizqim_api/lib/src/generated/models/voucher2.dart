// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'branch21.dart';
import 'sale6.dart';
import 'buyer6.dart';
import 'fiscal25.dart';
import 'blocked7.dart';
import 'print6.dart';

part 'voucher2.g.dart';

@JsonSerializable()
class Voucher2 {
  const Voucher2({
    required this.uuid,
    required this.code,
    required this.kind,
    required this.faceValue,
    required this.balance,
    required this.currency,
    required this.validFrom,
    required this.validUntil,
    required this.status,
    required this.statusLabel,
    required this.branch,
    required this.sale,
    required this.buyer,
    required this.note,
    required this.fiscal,
    required this.blocked,
    required this.print,
    required this.createdAt,
  });

  factory Voucher2.fromJson(Map<String, Object?> json) =>
      _$Voucher2FromJson(json);

  final String uuid;
  final String code;
  final String kind;
  @JsonKey(name: 'face_value')
  final String faceValue;
  final String balance;
  final String currency;
  @JsonKey(name: 'valid_from')
  final String validFrom;
  @JsonKey(name: 'valid_until')
  final String? validUntil;
  final String status;
  @JsonKey(name: 'status_label')
  final String statusLabel;
  final Branch21? branch;
  final Sale6 sale;
  final Buyer6 buyer;
  final String? note;
  final Fiscal25 fiscal;
  final Blocked7? blocked;
  final Print6? print;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$Voucher2ToJson(this);
}
