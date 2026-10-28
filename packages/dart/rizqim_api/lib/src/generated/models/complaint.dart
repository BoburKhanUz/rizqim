// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'complaint_reply.dart';
import 'complaint_support_status.dart';
import 'order.dart';
import 'user.dart';

part 'complaint.g.dart';

@JsonSerializable()
class Complaint {
  const Complaint({
    required this.id,
    required this.uuid,
    required this.orderId,
    required this.userId,
    required this.type,
    required this.description,
    required this.status,
    required this.reply,
    required this.resolutionComment,
    required this.resolvedBy,
    required this.createdAt,
    required this.updatedAt,
    required this.supportStatus,
    required this.supportStatusChangedAt,
    required this.answeredAt,
    required this.resolvedAt,
    this.order,
    this.user,
    this.resolver,
    this.assignee,
    this.replies,
  });

  factory Complaint.fromJson(Map<String, Object?> json) =>
      _$ComplaintFromJson(json);

  final int id;
  final String uuid;
  @JsonKey(name: 'order_id')
  final int orderId;
  @JsonKey(name: 'user_id')
  final int userId;
  final String type;
  final String description;
  final String status;
  final String? reply;
  @JsonKey(name: 'resolution_comment')
  final String? resolutionComment;
  @JsonKey(name: 'resolved_by')
  final int? resolvedBy;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  @JsonKey(name: 'support_status')
  final ComplaintSupportStatus supportStatus;
  @JsonKey(name: 'support_status_changed_at')
  final DateTime? supportStatusChangedAt;
  @JsonKey(name: 'answered_at')
  final DateTime? answeredAt;
  @JsonKey(name: 'resolved_at')
  final DateTime? resolvedAt;
  final Order? order;
  final User? user;
  final User? resolver;
  final User? assignee;
  final List<ComplaintReply>? replies;

  Map<String, Object?> toJson() => _$ComplaintToJson(this);
}
