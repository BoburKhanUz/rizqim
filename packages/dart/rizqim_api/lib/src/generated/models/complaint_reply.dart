// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'complaint.dart';
import 'user.dart';

part 'complaint_reply.g.dart';

@JsonSerializable()
class ComplaintReply {
  const ComplaintReply({
    required this.id,
    required this.complaintId,
    required this.authorId,
    required this.body,
    required this.cannedReplyId,
    required this.channel,
    required this.createdAt,
    required this.updatedAt,
    this.complaint,
    this.author,
  });

  factory ComplaintReply.fromJson(Map<String, Object?> json) =>
      _$ComplaintReplyFromJson(json);

  final int id;
  @JsonKey(name: 'complaint_id')
  final int complaintId;
  @JsonKey(name: 'author_id')
  final int? authorId;
  final String body;
  @JsonKey(name: 'canned_reply_id')
  final int? cannedReplyId;
  final String channel;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  final Complaint? complaint;
  final User? author;

  Map<String, Object?> toJson() => _$ComplaintReplyToJson(this);
}
