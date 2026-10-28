// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'store.dart';
import 'user.dart';

part 'payout_request.g.dart';

@JsonSerializable()
class PayoutRequest {
  const PayoutRequest({
    required this.id,
    required this.storeId,
    required this.amount,
    required this.cardNumber,
    required this.status,
    required this.processedBy,
    required this.processedAt,
    required this.notes,
    required this.createdAt,
    required this.updatedAt,
    required this.resolutionNotes,
    required this.firstApprovedBy,
    required this.firstApprovedAt,
    required this.firstApprovalNote,
    required this.nettedAmount,
    required this.transferredAmount,
    required this.netting,
    this.store,
    this.firstApprover,
  });

  factory PayoutRequest.fromJson(Map<String, Object?> json) =>
      _$PayoutRequestFromJson(json);

  final int id;
  @JsonKey(name: 'store_id')
  final int storeId;
  final String amount;
  @JsonKey(name: 'card_number')
  final String cardNumber;
  final String status;
  @JsonKey(name: 'processed_by')
  final dynamic processedBy;
  @JsonKey(name: 'processed_at')
  final DateTime? processedAt;
  final String? notes;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  @JsonKey(name: 'resolution_notes')
  final String? resolutionNotes;
  @JsonKey(name: 'first_approved_by')
  final int? firstApprovedBy;
  @JsonKey(name: 'first_approved_at')
  final DateTime? firstApprovedAt;
  @JsonKey(name: 'first_approval_note')
  final String? firstApprovalNote;
  @JsonKey(name: 'netted_amount')
  final String? nettedAmount;
  @JsonKey(name: 'transferred_amount')
  final String? transferredAmount;
  final List<dynamic>? netting;
  final Store? store;
  @JsonKey(name: 'first_approver')
  final User? firstApprover;

  Map<String, Object?> toJson() => _$PayoutRequestToJson(this);
}
