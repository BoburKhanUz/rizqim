// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'pending_receipts2.g.dart';

@JsonSerializable()
class PendingReceipts2 {
  const PendingReceipts2({required this.bills, required this.refunds});

  factory PendingReceipts2.fromJson(Map<String, Object?> json) =>
      _$PendingReceipts2FromJson(json);

  final List<String> bills;
  final List<String> refunds;

  Map<String, Object?> toJson() => _$PendingReceipts2ToJson(this);
}
