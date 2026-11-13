// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'branch9.dart';
import 'sale.dart';
import 'buyer.dart';
import 'fiscal4.dart';
import 'blocked.dart';
import 'print.dart';

part 'vouchers.g.dart';

@JsonSerializable()
class Vouchers {
  const Vouchers({
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

  factory Vouchers.fromJson(Map<String, Object?> json) =>
      _$VouchersFromJson(json);

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
  final Branch9? branch;
  final Sale sale;
  final Buyer buyer;
  final String? note;
  final Fiscal4 fiscal;
  final Blocked? blocked;
  final Print print;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$VouchersToJson(this);
}
